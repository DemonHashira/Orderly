<?php

use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

test('csv export succeeds for owner', function () {
    $organization = Organization::factory()->create();
    $user = createProductExportUser($organization->id, 'Owner');

    Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'ABC-1',
        'name' => 'Product A',
        'sale_price' => '10.00',
        'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    $response = $this->get('/api/products/export?format=csv', ['Accept' => 'application/json']);

    $response->assertOk();
    expect((string) $response->headers->get('content-disposition'))->toContain('.csv')
        ->and(readDownloadedFileContent($response))->toContain('ABC-1');
});

test('xlsx export succeeds for owner', function () {
    $organization = Organization::factory()->create();
    $user = createProductExportUser($organization->id, 'Owner');

    Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'XYZ-2',
        'name' => 'Product B',
        'sale_price' => '20.00',
    ]);

    Sanctum::actingAs($user);

    $response = $this->get('/api/products/export?format=xlsx', ['Accept' => 'application/json']);

    $response->assertOk();
    expect((string) $response->headers->get('content-disposition'))->toContain('.xlsx');
});

test('export is tenant scoped', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $user = createProductExportUser($organization->id, 'Owner');

    Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'IN-ORG',
        'name' => 'Org Product',
        'sale_price' => '10.00',
    ]);

    Product::factory()->create([
        'organization_id' => $otherOrganization->id,
        'sku' => 'OUT-ORG',
        'name' => 'Other Product',
        'sale_price' => '15.00',
    ]);

    Sanctum::actingAs($user);

    $response = $this->get('/api/products/export?format=csv', ['Accept' => 'application/json']);

    $response->assertOk();
    $content = readDownloadedFileContent($response);

    expect($content)->toContain('IN-ORG')
        ->not->toContain('OUT-ORG');
});

test('export filters by is_active and q', function () {
    $organization = Organization::factory()->create();
    $user = createProductExportUser($organization->id, 'Owner');

    Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'MATCH-1',
        'name' => 'Blue Shirt',
        'sale_price' => '10.00',
        'is_active' => true,
    ]);

    Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'MISS-1',
        'name' => 'Red Pants',
        'sale_price' => '15.00',
        'is_active' => false,
    ]);

    Sanctum::actingAs($user);

    $response = $this->get('/api/products/export?format=csv&is_active=1&q=blue', ['Accept' => 'application/json']);

    $response->assertOk();
    $content = readDownloadedFileContent($response);

    expect($content)->toContain('MATCH-1')
        ->not->toContain('MISS-1');
});

test('export permission is enforced', function () {
    $organization = Organization::factory()->create();
    $user = createProductExportUser($organization->id, 'Order Manager');

    Sanctum::actingAs($user);

    $this->get('/api/products/export?format=csv', ['Accept' => 'application/json'])
        ->assertStatus(403);
});

test('invalid export format returns 422', function () {
    $organization = Organization::factory()->create();
    $user = createProductExportUser($organization->id, 'Owner');

    Sanctum::actingAs($user);

    $this->get('/api/products/export?format=pdf', ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['format']);
});

test('inventory manager cannot export products', function () {
    $organization = Organization::factory()->create();
    $user = createProductExportUser($organization->id, 'Inventory Manager');

    Sanctum::actingAs($user);

    $this->get('/api/products/export?format=csv', ['Accept' => 'application/json'])
        ->assertStatus(403);
});

function createProductExportUser(int $organizationId, string $role): User
{
    $user = User::factory()->create(['organization_id' => $organizationId]);
    $user->assignRole($role);

    return $user;
}

function readDownloadedFileContent(TestResponse $response): string
{
    $baseResponse = $response->baseResponse;

    if (! method_exists($baseResponse, 'getFile')) {
        return '';
    }

    $file = $baseResponse->getFile();

    return (string) file_get_contents($file->getPathname());
}
