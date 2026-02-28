<?php

namespace App\Services\Import;

use App\Imports\ProductsImport;
use App\Models\InventoryStock;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

final class ProductImportService
{
    private const array REQUIRED_HEADERS = ['sku', 'name', 'sale_price'];

    public function import(UploadedFile $file, int $organizationId): array
    {
        $rowsBySheet = Excel::toArray(new ProductsImport, $file);
        $sheetRows = $rowsBySheet[0] ?? [];

        if ($sheetRows === []) {
            throw ValidationException::withMessages([
                'file' => ['Import file is empty.'],
            ]);
        }

        $headerRow = array_shift($sheetRows);
        $headerMap = $this->buildHeaderMap(is_array($headerRow) ? $headerRow : []);
        $this->assertRequiredHeaders($headerMap);

        $dataRows = [];

        foreach ($sheetRows as $index => $rawRow) {
            if (! is_array($rawRow) || $this->isEmptyRow($rawRow)) {
                continue;
            }

            $dataRows[] = [
                'row_number' => $index + 2,
                'values' => $rawRow,
            ];
        }

        $maxRows = (int) config('products.import.max_rows', 5000);
        if (count($dataRows) > $maxRows) {
            throw ValidationException::withMessages([
                'file' => ["Import exceeds maximum allowed rows ({$maxRows})."],
            ]);
        }

        $productsBySku = [];

        $existingProducts = Product::query()
            ->forOrg($organizationId)
            ->get(['id', 'sku']);

        foreach ($existingProducts as $product) {
            $productsBySku[$this->normalizeSku((string) $product->sku)] = $product;
        }

        $summary = [
            'total_rows' => count($dataRows),
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $seenSkusInFile = [];

        foreach ($dataRows as $entry) {
            $rowNumber = (int) $entry['row_number'];
            $rowValues = (array) $entry['values'];

            $row = $this->extractRow($rowValues, $headerMap);

            $firstError = $this->firstValidationError($row, $seenSkusInFile);
            if ($firstError !== null) {
                $summary['failed']++;
                $summary['errors'][] = [
                    'row' => $rowNumber,
                    'message' => $firstError,
                ];

                continue;
            }

            $normalizedSku = $this->normalizeSku((string) $row['sku']);
            $seenSkusInFile[$normalizedSku] = true;

            $persisted = $this->upsertProduct($organizationId, $row, $productsBySku[$normalizedSku] ?? null);

            if ($persisted['action'] === 'created') {
                $summary['created']++;
                $productsBySku[$normalizedSku] = $persisted['product'];
            }

            if ($persisted['action'] === 'updated') {
                $summary['updated']++;
                $productsBySku[$normalizedSku] = $persisted['product'];
            }
        }

        return $summary;
    }

    private function buildHeaderMap(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $index => $value) {
            $normalized = $this->normalizeHeader((string) $value);
            if ($normalized === '') {
                continue;
            }

            $map[$normalized] = (int) $index;
        }

        return $map;
    }

    private function assertRequiredHeaders(array $headerMap): void
    {
        $missing = [];

        foreach (self::REQUIRED_HEADERS as $header) {
            if (! array_key_exists($header, $headerMap)) {
                $missing[] = $header;
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => ['Missing required headers: '.implode(', ', $missing)],
            ]);
        }
    }

    private function extractRow(array $values, array $headerMap): array
    {

        return array_map(function ($index) use ($values) {
            return $values[$index] ?? null;
        }, $headerMap);
    }

    private function firstValidationError(array $row, array $seenSkusInFile): ?string
    {
        $sku = $this->normalizeSku((string) ($row['sku'] ?? ''));
        if ($sku === '') {
            return 'sku is required.';
        }

        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            return 'name is required.';
        }

        $rawSalePrice = trim((string) ($row['sale_price'] ?? ''));
        if ($rawSalePrice === '') {
            return 'sale_price is required.';
        }

        if (! $this->isDecimalValue($rawSalePrice)) {
            return 'sale_price must be numeric.';
        }

        if ((float) $rawSalePrice < 0) {
            return 'sale_price must be greater than or equal to 0.';
        }

        $isActiveValid = true;
        $this->normalizeIsActive($row['is_active'] ?? null, $isActiveValid);

        if (! $isActiveValid) {
            return 'is_active must be a boolean value.';
        }

        if (array_key_exists($sku, $seenSkusInFile)) {
            return "Duplicate SKU in file: {$sku}";
        }

        return null;
    }

    private function upsertProduct(int $organizationId, array $row, ?Product $existing): array
    {
        $normalizedSku = $this->normalizeSku((string) $row['sku']);
        $name = trim((string) $row['name']);
        $salePrice = $this->normalizeSalePrice((string) $row['sale_price']);
        $descriptionRaw = array_key_exists('description', $row) ? trim((string) $row['description']) : '';
        $description = $descriptionRaw === '' ? null : $descriptionRaw;

        $isActiveValid = true;
        $isActive = $this->normalizeIsActive($row['is_active'] ?? null, $isActiveValid);

        return DB::transaction(function () use ($organizationId, $existing, $normalizedSku, $name, $salePrice, $description, $isActive) {
            if ($existing !== null) {
                $existing->forceFill([
                    'name' => $name,
                    'sale_price' => $salePrice,
                    'description' => $description,
                    'is_active' => $isActive,
                ])->save();

                InventoryStock::query()->updateOrCreate(
                    [
                        'organization_id' => $organizationId,
                        'product_id' => (int) $existing->id,
                    ],
                    [
                        'qty_on_hand' => 0,
                        'qty_reserved' => 0,
                        'reorder_threshold' => null,
                    ],
                );

                return [
                    'action' => 'updated',
                    'product' => $existing,
                ];
            }

            $product = Product::query()->create([
                'organization_id' => $organizationId,
                'sku' => $normalizedSku,
                'name' => $name,
                'sale_price' => $salePrice,
                'description' => $description,
                'is_active' => $isActive,
            ]);

            InventoryStock::query()->updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'product_id' => (int) $product->id,
                ],
                [
                    'qty_on_hand' => 0,
                    'qty_reserved' => 0,
                    'reorder_threshold' => null,
                ],
            );

            return [
                'action' => 'created',
                'product' => $product,
            ];
        });
    }

    private function normalizeHeader(string $header): string
    {
        $normalized = strtolower(trim($header));

        return str_replace(' ', '_', $normalized);
    }

    private function normalizeSku(string $sku): string
    {
        return strtoupper(trim($sku));
    }

    private function normalizeSalePrice(string $value): string
    {
        return number_format((float) trim($value), 2, '.', '');
    }

    private function isDecimalValue(string $value): bool
    {
        return preg_match('/^-?\d+(\.\d+)?$/', trim($value)) === 1;
    }

    private function normalizeIsActive(mixed $value, bool &$valid): bool
    {
        if ($value === null) {
            $valid = true;

            return true;
        }

        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            $valid = true;

            return true;
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            $valid = true;

            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            $valid = true;

            return false;
        }

        $valid = false;

        return true;
    }

    private function isEmptyRow(array $row): bool
    {
        return array_all($row, fn ($value) => trim((string) $value) === '');

    }
}
