<?php

namespace Database\Seeders;

use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class InventoryScenarioSeeder extends Seeder
{
    private const array ARCHIVE_SKUS = [
        'MANGA-JJK-001',
        'MANGA-AOT-002',
        'MANGA-SPY-003',
        'LN-MUSH-001',
        'LN-REZERO-002',
        'BOX-AOT-001',
        'FIG-GOJO-001',
        'MERCH-POSTER-001',
    ];

    public function run(): void
    {
        $org = Organization::query()->where('slug', 'otaku-store')->firstOrFail();

        $inventoryUserId = User::query()
            ->where('organization_id', $org->id)
            ->role('Inventory Manager')
            ->value('id');

        $stocks = InventoryStock::query()
            ->where('organization_id', $org->id)
            ->with('product')
            ->get()
            ->filter(fn (InventoryStock $stock): bool => $stock->product !== null)
            ->values();

        if ($stocks->isEmpty()) {
            throw new RuntimeException('InventoryScenarioSeeder requires seeded inventory stocks.');
        }

        $this->repairReservedFloorViolations($stocks, $inventoryUserId);

        $usedProductIds = [];

        $reservedCandidates = $stocks
            ->filter(fn (InventoryStock $stock): bool => $stock->product->is_active && $stock->qty_reserved > 0)
            ->sortByDesc(fn (InventoryStock $stock): int => $this->available($stock))
            ->values();

        if ($reservedCandidates->count() < 2) {
            throw new RuntimeException('InventoryScenarioSeeder requires at least two reserved stock rows.');
        }

        $this->applyTargetAvailableAdjustment(
            $reservedCandidates[0],
            0,
            'Cycle count correction',
            now()->subDays(5),
            $inventoryUserId,
        );
        $usedProductIds[] = (int) $reservedCandidates[0]->product_id;

        $this->applyTargetAvailableAdjustment(
            $reservedCandidates[1],
            2,
            'Shelf recount',
            now()->subDays(11),
            $inventoryUserId,
        );
        $usedProductIds[] = (int) $reservedCandidates[1]->product_id;

        $restockCandidates = $this->selectActiveCandidates(
            $stocks,
            $usedProductIds,
            fn (InventoryStock $stock): bool => $this->available($stock) <= 12,
            descendingAvailable: false,
        );

        foreach ($this->takeCandidates($restockCandidates, 3) as $index => $stock) {
            $this->applyMovement(
                $stock,
                'restock',
                [14, 20, 26][$index],
                ['Supplier delivery', 'Vendor replenishment', 'Backroom restock'][$index],
                now()->subDays([3, 29, 67][$index]),
                $inventoryUserId,
            );

            $usedProductIds[] = (int) $stock->product_id;
        }

        $damageCandidates = $this->selectActiveCandidates(
            $stocks,
            $usedProductIds,
            fn (InventoryStock $stock): bool => $this->available($stock) >= 6,
            descendingAvailable: true,
        );

        foreach ($this->takeCandidates($damageCandidates, 3) as $index => $stock) {
            $this->applyMovement(
                $stock,
                'damage',
                [-2, -3, -4][$index],
                ['Damaged during handling', 'Warehouse damage write-off', 'Packaging failure'][$index],
                now()->subDays([7, 23, 52][$index]),
                $inventoryUserId,
            );

            $usedProductIds[] = (int) $stock->product_id;
        }

        $positiveAdjustment = $this->selectActiveCandidates(
            $stocks,
            $usedProductIds,
            fn (InventoryStock $stock): bool => $this->available($stock) >= 4 && $this->available($stock) <= 40,
            descendingAvailable: false,
        )->first();

        if (! $positiveAdjustment instanceof InventoryStock) {
            throw new RuntimeException('InventoryScenarioSeeder could not find an adjustment-positive candidate.');
        }

        $this->applyMovement(
            $positiveAdjustment,
            'adjustment',
            4,
            'Backroom recount',
            now()->subDays(37),
            $inventoryUserId,
        );
        $usedProductIds[] = (int) $positiveAdjustment->product_id;

        $negativeAdjustment = $this->selectActiveCandidates(
            $stocks,
            $usedProductIds,
            fn (InventoryStock $stock): bool => $this->available($stock) >= 8,
            descendingAvailable: true,
        )->first();

        if (! $negativeAdjustment instanceof InventoryStock) {
            throw new RuntimeException('InventoryScenarioSeeder could not find an adjustment-negative candidate.');
        }

        $this->applyMovement(
            $negativeAdjustment,
            'adjustment',
            -3,
            'Cycle count correction',
            now()->subDays(61),
            $inventoryUserId,
        );

        $this->archiveProducts($org->id);

        if (! $this->hasHealthyStockState($stocks)) {
            throw new RuntimeException('InventoryUiSeeder failed to produce a healthy stock row.');
        }
    }

    private function selectActiveCandidates(
        Collection $stocks,
        array $usedProductIds,
        callable $predicate,
        bool $descendingAvailable,
    ): Collection {
        $filtered = $stocks
            ->filter(
                fn (InventoryStock $stock): bool => $stock->product->is_active
                    && ! in_array((int) $stock->product_id, $usedProductIds, true)
                    && $predicate($stock),
            );

        return $descendingAvailable
            ? $filtered->sortByDesc(fn (InventoryStock $stock): int => $this->available($stock))->values()
            : $filtered->sortBy(fn (InventoryStock $stock): int => $this->available($stock))->values();
    }

    private function takeCandidates(Collection $candidates, int $count): Collection
    {
        if ($candidates->count() < $count) {
            throw new RuntimeException("InventoryScenarioSeeder requires {$count} inventory candidates.");
        }

        return $candidates->take($count)->values();
    }

    private function applyTargetAvailableAdjustment(
        InventoryStock $stock,
        int $targetAvailable,
        string $reason,
        Carbon $occurredAt,
        ?int $inventoryUserId,
    ): void {
        $delta = ($stock->qty_reserved + $targetAvailable) - $stock->qty_on_hand;

        if ($delta === 0) {
            return;
        }

        $this->applyMovement(
            $stock,
            'adjustment',
            $delta,
            $reason,
            $occurredAt,
            $inventoryUserId,
        );
    }

    private function applyMovement(
        InventoryStock $stock,
        string $type,
        int $qtyDelta,
        string $reason,
        Carbon $occurredAt,
        ?int $inventoryUserId,
    ): void {
        $newQtyOnHand = $stock->qty_on_hand + $qtyDelta;

        if ($newQtyOnHand < $stock->qty_reserved || $newQtyOnHand < 0) {
            throw new RuntimeException("InventoryScenarioSeeder movement would violate stock invariants for SKU {$stock->product->sku}.");
        }

        $movement = InventoryMovement::query()->create([
            'organization_id' => (int) $stock->organization_id,
            'product_id' => (int) $stock->product_id,
            'performed_by_user_id' => $inventoryUserId,
            'type' => $type,
            'qty_delta' => $qtyDelta,
            'reason' => $reason,
            'reference_type' => 'seed',
            'reference_id' => (int) $stock->product_id,
        ]);

        $movement->forceFill([
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ])->save();

        $stock->forceFill([
            'qty_on_hand' => $newQtyOnHand,
        ])->save();

        $stock->qty_on_hand = $newQtyOnHand;
    }

    private function archiveProducts(int $organizationId): void
    {
        $archivedCount = Product::query()
            ->where('organization_id', $organizationId)
            ->whereIn('sku', self::ARCHIVE_SKUS)
            ->update(['is_active' => false]);

        if ($archivedCount !== count(self::ARCHIVE_SKUS)) {
            throw new RuntimeException('InventoryScenarioSeeder could not archive the full deterministic product set.');
        }
    }

    private function repairReservedFloorViolations(Collection $stocks, ?int $inventoryUserId): void
    {
        $violatingStocks = $stocks
            ->filter(fn (InventoryStock $stock): bool => $stock->qty_on_hand < $stock->qty_reserved)
            ->values();

        foreach ($violatingStocks as $index => $stock) {
            $delta = $stock->qty_reserved - $stock->qty_on_hand;

            $this->applyMovement(
                $stock,
                'adjustment',
                $delta,
                'Reserved stock integrity correction',
                now()->subDays(13)->addMinutes($index),
                $inventoryUserId,
            );
        }
    }

    private function hasHealthyStockState(Collection $stocks): bool
    {
        return $stocks->contains(
            fn (InventoryStock $stock): bool => $this->available($stock) >= 25,
        );
    }

    private function available(InventoryStock $stock): int
    {
        return (int) $stock->qty_on_hand - (int) $stock->qty_reserved;
    }
}
