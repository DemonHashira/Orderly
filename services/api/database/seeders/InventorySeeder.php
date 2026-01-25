<?php

namespace Database\Seeders;

use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::query()->where('slug', 'otaku-corner')->firstOrFail();

        $inventoryUser = User::query()
            ->where('organization_id', $org->id)
            ->role('Inventory Controller')
            ->first()
            ?? User::query()->where('organization_id', $org->id)->first();

        $products = Product::query()
            ->where('organization_id', $org->id)
            ->orderBy('id')
            ->get();

        foreach ($products as $product) {
            [$qtyOnHand, $reorderThreshold] = $this->initialStockForSku($product->sku);

            InventoryStock::query()->updateOrCreate(
                [
                    'organization_id' => $org->id,
                    'product_id' => $product->id,
                ],
                [
                    'qty_on_hand' => $qtyOnHand,
                    'qty_reserved' => 0,
                    'reorder_threshold' => $reorderThreshold,
                ],
            );

            InventoryMovement::query()->updateOrCreate(
                [
                    'organization_id' => $org->id,
                    'product_id' => $product->id,
                    'type' => 'restock',
                    'reference_type' => 'seed',
                    'reference_id' => $product->id,
                ],
                [
                    'performed_by_user_id' => $inventoryUser?->id,
                    'qty_delta' => $qtyOnHand,
                    'reason' => 'Initial stock load (seeded).',
                ],
            );
        }
    }

    private function initialStockForSku(?string $sku): array
    {
        $sku = $sku ?? '';

        if (str_starts_with($sku, 'FIG-')) {
            return [fake()->numberBetween(2, 15), 2];
        }

        if (str_starts_with($sku, 'MERCH-')) {
            return [fake()->numberBetween(10, 120), 20];
        }

        return [fake()->numberBetween(15, 90), 10];
    }
}
