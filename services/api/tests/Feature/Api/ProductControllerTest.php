<?php

use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

test('index returns paginated products scoped to organization and supports filters', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $user = createProductApiUser($organization->id, 'Inventory Manager');

    $matching = Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'ABC-100',
        'name' => 'Blue Figure',
        'is_active' => true,
    ]);

    Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'XYZ-100',
        'name' => 'Red Figure',
        'is_active' => false,
    ]);

    Product::factory()->create([
        'organization_id' => $otherOrganization->id,
        'sku' => 'ABC-200',
        'name' => 'Blue Figure Other Org',
        'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/products?q=abc&is_active=1&per_page=10')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matching->id)
        ->assertJsonStructure([
            'data' => [[
                'id',
                'sku',
                'name',
                'sale_price',
                'description',
                'is_active',
                'created_at',
                'updated_at',
            ]],
            'links',
            'meta',
        ]);
});

test('show returns product for same organization and 404 for cross organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $user = createProductApiUser($organization->id, 'Order Manager');

    $product = Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'SHOW-001',
    ]);

    $otherProduct = Product::factory()->create([
        'organization_id' => $otherOrganization->id,
        'sku' => 'SHOW-OTHER',
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/products/'.$product->id)
        ->assertStatus(200)
        ->assertJsonPath('data.id', $product->id);

    $this->getJson('/api/products/'.$otherProduct->id)
        ->assertStatus(404);
});

test('owner can create product and normalize sku', function () {
    $organization = Organization::factory()->create();
    $user = createProductApiUser($organization->id, 'Owner');

    Sanctum::actingAs($user);

    $this->postJson('/api/products', [
        'sku' => '  abC-900  ',
        'name' => 'New Product',
        'sale_price' => '19.99',
        'description' => 'demo',
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.sku', 'ABC-900')
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('products', [
        'organization_id' => $organization->id,
        'sku' => 'ABC-900',
        'name' => 'New Product',
    ]);

    $product = Product::query()
        ->where('organization_id', $organization->id)
        ->where('sku', 'ABC-900')
        ->firstOrFail();

    $this->assertDatabaseHas('inventory_stocks', [
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_on_hand' => 0,
        'qty_reserved' => 0,
        'reorder_threshold' => null,
    ]);
});

test('create rejects prohibited organization_id payload', function () {
    $organization = Organization::factory()->create();
    $user = createProductApiUser($organization->id, 'Owner');

    Sanctum::actingAs($user);

    $this->postJson('/api/products', [
        'organization_id' => 999,
        'sku' => 'ORG-001',
        'name' => 'Should Fail',
        'sale_price' => '19.99',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['organization_id']);
});

test('create and update require products.manage permission', function () {
    $organization = Organization::factory()->create();
    $user = createProductApiUser($organization->id, 'Order Manager');

    $product = Product::factory()->create([
        'organization_id' => $organization->id,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/products', [
        'sku' => 'NOPE-1',
        'name' => 'No Manage',
        'sale_price' => '10.00',
    ])->assertStatus(403);

    $this->patchJson('/api/products/'.$product->id, [
        'name' => 'Nope',
    ])->assertStatus(403);

    $this->postJson('/api/products/'.$product->id.'/archive')
        ->assertStatus(403);
});

test('owner can patch product and normalize sku', function () {
    $organization = Organization::factory()->create();
    $user = createProductApiUser($organization->id, 'Owner');

    $product = Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'PATCH-001',
        'name' => 'Before Name',
        'sale_price' => '5.00',
    ]);

    Sanctum::actingAs($user);

    $this->patchJson('/api/products/'.$product->id, [
        'sku' => ' patch-002 ',
        'sale_price' => '7.50',
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.sku', 'PATCH-002')
        ->assertJsonPath('data.sale_price', '7.50')
        ->assertJsonPath('data.name', 'Before Name');

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'sku' => 'PATCH-002',
        'sale_price' => '7.50',
        'name' => 'Before Name',
    ]);
});

test('create rejects case insensitive duplicate sku within organization', function () {
    $organization = Organization::factory()->create();
    $user = createProductApiUser($organization->id, 'Owner');

    Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'DUP-001',
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/products', [
        'sku' => 'dup-001',
        'name' => 'Duplicate',
        'sale_price' => '9.00',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['sku']);
});

test('archive endpoint sets is_active false and is idempotent', function () {
    $organization = Organization::factory()->create();
    $user = createProductApiUser($organization->id, 'Owner');

    $product = Product::factory()->create([
        'organization_id' => $organization->id,
        'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/products/'.$product->id.'/archive')
        ->assertStatus(200)
        ->assertJsonPath('data.is_active', false);

    $this->postJson('/api/products/'.$product->id.'/archive')
        ->assertStatus(200)
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'is_active' => false,
    ]);
});

test('index and show require products.view permission', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $product = Product::factory()->create([
        'organization_id' => $organization->id,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/products')->assertStatus(403);
    $this->getJson('/api/products/'.$product->id)->assertStatus(403);
});

test('inventory manager is read only for product catalog mutations', function () {
    $organization = Organization::factory()->create();
    $user = createProductApiUser($organization->id, 'Inventory Manager');

    $product = Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'INV-READONLY',
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/products', [
        'sku' => 'INV-NEW',
        'name' => 'Should Fail',
        'sale_price' => '19.99',
    ])->assertStatus(403);

    $this->patchJson('/api/products/'.$product->id, [
        'name' => 'Should Fail',
    ])->assertStatus(403);

    $this->postJson('/api/products/'.$product->id.'/archive')
        ->assertStatus(403);
});

test('products manage permission can view a same-organization product for edit flows', function () {
    $organization = Organization::factory()->create();
    $product = Product::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $user = User::factory()->create(['organization_id' => $organization->id]);
    $user->givePermissionTo(Permission::findByName('products.manage', 'web'));

    Sanctum::actingAs($user);

    $this->getJson('/api/products/'.$product->id)
        ->assertStatus(200)
        ->assertJsonPath('data.id', $product->id);

    $this->getJson('/api/products')->assertStatus(403);
});

function createProductApiUser(int $organizationId, string $role): User
{
    $user = User::factory()->create(['organization_id' => $organizationId]);
    $user->assignRole($role);

    return $user;
}
