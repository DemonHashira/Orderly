<?php

use App\Models\Organization;
use App\Models\Product;
use App\Services\Import\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('service preloads existing skus and avoids per-row select lookups', function () {
    $organization = Organization::factory()->create();

    Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'ABC-1',
        'name' => 'Old A',
        'sale_price' => '5.00',
    ]);

    Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'DEF-2',
        'name' => 'Old D',
        'sale_price' => '6.00',
    ]);

    $file = UploadedFile::fake()->createWithContent('products.csv', makeServiceCsvContent(
        headers: ['sku', 'name', 'sale_price'],
        rows: [
            ['abc-1', 'Updated A', '10.00'],
            ['def-2', 'Updated D', '20.00'],
            ['ghi-3', 'New G', '30.00'],
        ],
    ));

    DB::enableQueryLog();

    $summary = app(ProductImportService::class)->import($file, $organization->id);

    $queries = DB::getQueryLog();

    $productSelectCount = collect($queries)
        ->pluck('query')
        ->filter(fn (string $query): bool => str_contains(strtolower($query), 'select') && str_contains(strtolower($query), 'from "products"'))
        ->count();

    expect($summary['created'])->toBe(1)
        ->and($summary['updated'])->toBe(2)
        ->and($summary['failed'])->toBe(0)
        ->and($productSelectCount)->toBe(1);
});

test('service detects missing required headers', function () {
    $organization = Organization::factory()->create();

    $file = UploadedFile::fake()->createWithContent('products.csv', makeServiceCsvContent(
        headers: ['sku', 'name'],
        rows: [['ABC-1', 'Product A']],
    ));

    expect(fn () => app(ProductImportService::class)->import($file, $organization->id))
        ->toThrow(ValidationException::class);
});

test('service returns first validation issue only for a row', function () {
    $organization = Organization::factory()->create();

    $file = UploadedFile::fake()->createWithContent('products.csv', makeServiceCsvContent(
        headers: ['sku', 'name', 'sale_price'],
        rows: [['', '', '-1']],
    ));

    $summary = app(ProductImportService::class)->import($file, $organization->id);

    expect($summary['failed'])->toBe(1)
        ->and($summary['errors'][0]['message'])->toBe('sku is required.');
});

test('service normalizes decimal precision for sale_price', function () {
    $organization = Organization::factory()->create();

    $file = UploadedFile::fake()->createWithContent('products.csv', makeServiceCsvContent(
        headers: ['sku', 'name', 'sale_price'],
        rows: [['ABC-1', 'Product A', '12.5']],
    ));

    $summary = app(ProductImportService::class)->import($file, $organization->id);

    expect($summary['created'])->toBe(1);

    $this->assertDatabaseHas('products', [
        'organization_id' => $organization->id,
        'sku' => 'ABC-1',
        'sale_price' => '12.50',
    ]);
});

function makeServiceCsvContent(array $headers, array $rows): string
{
    $lines = [implode(',', $headers)];

    foreach ($rows as $row) {
        $escaped = array_map(static function ($value): string {
            $value = (string) $value;
            if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
                $value = '"'.str_replace('"', '""', $value).'"';
            }

            return $value;
        }, $row);

        $lines[] = implode(',', $escaped);
    }

    return implode("\n", $lines);
}
