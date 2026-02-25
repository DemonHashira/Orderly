<?php

use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

test('valid import creates products and normalizes sku', function () {
    $organization = Organization::factory()->create();
    $user = createProductImportUser($organization->id, 'Inventory Manager');

    Sanctum::actingAs($user);

    $file = UploadedFile::fake()->createWithContent('products.csv', makeCsvContent(
        headers: [' sku ', 'name', 'sale_price', 'description', 'is_active'],
        rows: [
            [' abc-1 ', 'Product A', '12.5', 'Desc A', '1'],
            ['XYZ-2', 'Product B', '10.00', '', '0'],
        ],
    ));

    $this->post('/api/products/import', ['file' => $file], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('total_rows', 2)
        ->assertJsonPath('created', 2)
        ->assertJsonPath('updated', 0)
        ->assertJsonPath('failed', 0);

    $this->assertDatabaseHas('products', [
        'organization_id' => $organization->id,
        'sku' => 'ABC-1',
        'name' => 'Product A',
        'sale_price' => '12.50',
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('products', [
        'organization_id' => $organization->id,
        'sku' => 'XYZ-2',
        'is_active' => false,
    ]);
});

test('import updates existing sku in same organization', function () {
    $organization = Organization::factory()->create();
    $user = createProductImportUser($organization->id, 'Inventory Manager');

    Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'ABC-1',
        'name' => 'Old Name',
        'sale_price' => '5.00',
        'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    $file = UploadedFile::fake()->createWithContent('products.csv', makeCsvContent(
        headers: ['sku', 'name', 'sale_price'],
        rows: [
            ['abc-1', 'Updated Name', '15.20'],
        ],
    ));

    $this->post('/api/products/import', ['file' => $file], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('created', 0)
        ->assertJsonPath('updated', 1)
        ->assertJsonPath('failed', 0);

    $this->assertDatabaseHas('products', [
        'organization_id' => $organization->id,
        'sku' => 'ABC-1',
        'name' => 'Updated Name',
        'sale_price' => '15.20',
    ]);
});

test('duplicate sku in same file is rejected case-insensitively', function () {
    $organization = Organization::factory()->create();
    $user = createProductImportUser($organization->id, 'Inventory Manager');

    Sanctum::actingAs($user);

    $file = UploadedFile::fake()->createWithContent('products.csv', makeCsvContent(
        headers: ['sku', 'name', 'sale_price'],
        rows: [
            ['ABC-1', 'Product A', '5.00'],
            [' abc-1 ', 'Product B', '6.00'],
        ],
    ));

    $this->post('/api/products/import', ['file' => $file], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('total_rows', 2)
        ->assertJsonPath('created', 1)
        ->assertJsonPath('updated', 0)
        ->assertJsonPath('failed', 1)
        ->assertJsonPath('errors.0.row', 3)
        ->assertJsonPath('errors.0.message', 'Duplicate SKU in file: ABC-1');
});

test('missing required headers returns 422 before row processing', function () {
    $organization = Organization::factory()->create();
    $user = createProductImportUser($organization->id, 'Inventory Manager');

    Sanctum::actingAs($user);

    $file = UploadedFile::fake()->createWithContent('products.csv', makeCsvContent(
        headers: ['sku', 'name'],
        rows: [
            ['ABC-1', 'Product A'],
        ],
    ));

    $this->post('/api/products/import', ['file' => $file], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

test('row limit over 5000 returns 422', function () {
    $organization = Organization::factory()->create();
    $user = createProductImportUser($organization->id, 'Inventory Manager');

    Sanctum::actingAs($user);

    $rows = [];
    for ($i = 1; $i <= 5001; $i++) {
        $rows[] = ["SKU-{$i}", "Product {$i}", '1.00'];
    }

    $file = UploadedFile::fake()->createWithContent('products.csv', makeCsvContent(
        headers: ['sku', 'name', 'sale_price'],
        rows: $rows,
    ));

    $this->post('/api/products/import', ['file' => $file], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

test('import is tenant scoped when same sku exists in another organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $user = createProductImportUser($organization->id, 'Inventory Manager');

    Product::factory()->create([
        'organization_id' => $otherOrganization->id,
        'sku' => 'ABC-1',
        'name' => 'Other Org Product',
        'sale_price' => '9.00',
    ]);

    Sanctum::actingAs($user);

    $file = UploadedFile::fake()->createWithContent('products.csv', makeCsvContent(
        headers: ['sku', 'name', 'sale_price'],
        rows: [
            ['abc-1', 'Org A Product', '11.00'],
        ],
    ));

    $this->post('/api/products/import', ['file' => $file], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('created', 1)
        ->assertJsonPath('updated', 0);

    $this->assertDatabaseHas('products', [
        'organization_id' => $otherOrganization->id,
        'sku' => 'ABC-1',
        'name' => 'Other Org Product',
        'sale_price' => '9.00',
    ]);

    $this->assertDatabaseHas('products', [
        'organization_id' => $organization->id,
        'sku' => 'ABC-1',
        'name' => 'Org A Product',
        'sale_price' => '11.00',
    ]);
});

test('permission is enforced for import', function () {
    $organization = Organization::factory()->create();
    $user = createProductImportUser($organization->id, 'Order Manager');

    Sanctum::actingAs($user);

    $file = UploadedFile::fake()->createWithContent('products.csv', makeCsvContent(
        headers: ['sku', 'name', 'sale_price'],
        rows: [['ABC-1', 'Product', '10.00']],
    ));

    $this->post('/api/products/import', ['file' => $file], ['Accept' => 'application/json'])
        ->assertStatus(403);
});

test('invalid file validation returns 422', function () {
    $organization = Organization::factory()->create();
    $user = createProductImportUser($organization->id, 'Inventory Manager');

    Sanctum::actingAs($user);

    $file = UploadedFile::fake()->image('products.png');

    $this->post('/api/products/import', ['file' => $file], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

test('import row error returns first validation issue only', function () {
    $organization = Organization::factory()->create();
    $user = createProductImportUser($organization->id, 'Inventory Manager');

    Sanctum::actingAs($user);

    $file = UploadedFile::fake()->createWithContent('products.csv', makeCsvContent(
        headers: ['sku', 'name', 'sale_price'],
        rows: [
            ['', '', '-5'],
        ],
    ));

    $this->post('/api/products/import', ['file' => $file], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('failed', 1)
        ->assertJsonPath('errors.0.message', 'sku is required.');
});

function createProductImportUser(int $organizationId, string $role): User
{
    $user = User::factory()->create(['organization_id' => $organizationId]);
    $user->assignRole($role);

    return $user;
}

function makeCsvContent(array $headers, array $rows): string
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
