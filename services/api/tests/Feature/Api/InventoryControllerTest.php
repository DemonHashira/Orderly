<?php

use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

test('inventory endpoints require authentication', function () {
    $this->getJson('/api/inventory/stocks')->assertStatus(401);
    $this->getJson('/api/inventory/movements')->assertStatus(401);
    $this->postJson('/api/inventory/movements', [])->assertStatus(401);
});

test('stocks index is tenant scoped and supports q and is_active filters', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $user = createInventoryApiUserWithRole($organization->id, 'Inventory Manager');

    $matchingProduct = Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'ABC-100',
        'name' => 'Blue Figure',
        'is_active' => true,
    ]);
    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $matchingProduct->id,
        'qty_on_hand' => 12,
        'qty_reserved' => 2,
    ]);

    $nonMatchingProduct = Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'ZZZ-100',
        'name' => 'Red Figure',
        'is_active' => false,
    ]);
    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $nonMatchingProduct->id,
    ]);

    $otherProduct = Product::factory()->create([
        'organization_id' => $otherOrganization->id,
        'sku' => 'ABC-999',
        'name' => 'Blue Figure Other Org',
        'is_active' => true,
    ]);
    InventoryStock::factory()->create([
        'organization_id' => $otherOrganization->id,
        'product_id' => $otherProduct->id,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/inventory/stocks?q=abc&is_active=1&per_page=10')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.product.id', $matchingProduct->id)
        ->assertJsonPath('data.0.qty_on_hand', 12)
        ->assertJsonPath('data.0.qty_reserved', 2)
        ->assertJsonPath('data.0.available', 10)
        ->assertJsonStructure([
            'data' => [[
                'product' => ['id', 'sku', 'name', 'is_active'],
                'qty_on_hand',
                'qty_reserved',
                'available',
            ]],
            'links',
            'meta',
        ]);
});

test('stocks and movements index require inventory view permission', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    Sanctum::actingAs($user);

    $this->getJson('/api/inventory/stocks')->assertStatus(403);
    $this->getJson('/api/inventory/movements')->assertStatus(403);
});

test('movements index is tenant scoped and supports filters', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $user = createInventoryApiUserWithRole($organization->id, 'Inventory Manager');

    $product = Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'MOV-1',
        'name' => 'Movement Product',
    ]);
    $otherProduct = Product::factory()->create([
        'organization_id' => $otherOrganization->id,
        'sku' => 'MOV-9',
        'name' => 'Other Product',
    ]);

    InventoryMovement::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => 'restock',
        'qty_delta' => 5,
        'created_at' => Carbon::parse('2026-02-10 10:00:00'),
    ]);
    InventoryMovement::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => 'damage',
        'qty_delta' => -2,
        'created_at' => Carbon::parse('2026-02-12 15:00:00'),
    ]);
    InventoryMovement::factory()->create([
        'organization_id' => $otherOrganization->id,
        'product_id' => $otherProduct->id,
        'type' => 'restock',
        'qty_delta' => 50,
        'created_at' => Carbon::parse('2026-02-12 15:00:00'),
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/inventory/movements?product_id='.$product->id.'&type=damage&from=2026-02-01&to=2026-02-28&per_page=10')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'damage')
        ->assertJsonPath('data.0.quantity_delta', -2)
        ->assertJsonPath('data.0.product.id', $product->id)
        ->assertJsonStructure([
            'data' => [[
                'id',
                'product_id',
                'type',
                'quantity_delta',
                'reason',
                'created_at',
                'product' => ['id', 'sku', 'name'],
            ]],
            'links',
            'meta',
        ]);
});

test('movements index accepts arbitrary type filter string', function () {
    $organization = Organization::factory()->create();
    $user = createInventoryApiUserWithRole($organization->id, 'Inventory Manager');

    Sanctum::actingAs($user);

    $this->getJson('/api/inventory/movements?type=custom_type_value')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('movements index supports q search by product sku or name', function () {
    $organization = Organization::factory()->create();
    $user = createInventoryApiUserWithRole($organization->id, 'Inventory Manager');

    $matchingProduct = Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'Q-SEARCH-100',
        'name' => 'Blue Capsule',
    ]);
    $nonMatchingProduct = Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'Q-OTHER-200',
        'name' => 'Red Capsule',
    ]);

    InventoryMovement::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $matchingProduct->id,
        'type' => 'adjustment',
        'qty_delta' => 3,
    ]);
    InventoryMovement::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $nonMatchingProduct->id,
        'type' => 'adjustment',
        'qty_delta' => 2,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/inventory/movements?q=q-search')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.product.id', $matchingProduct->id);
});

test('movements index clamps per_page to 100', function () {
    $organization = Organization::factory()->create();
    $user = createInventoryApiUserWithRole($organization->id, 'Inventory Manager');
    $product = Product::factory()->create(['organization_id' => $organization->id]);

    for ($i = 0; $i < 105; $i++) {
        InventoryMovement::factory()->create([
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'type' => 'adjustment',
            'qty_delta' => 1,
        ]);
    }

    Sanctum::actingAs($user);

    $this->getJson('/api/inventory/movements?per_page=1000')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100)
        ->assertJsonCount(100, 'data');
});

test('store movement requires inventory movement create permission', function () {
    $organization = Organization::factory()->create();
    $user = createInventoryApiUserWithRole($organization->id, 'Order Manager');
    $product = Product::factory()->create(['organization_id' => $organization->id, 'is_active' => true]);
    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_on_hand' => 10,
        'qty_reserved' => 0,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/inventory/movements', [
        'product_id' => $product->id,
        'type' => 'restock',
        'quantity_delta' => 5,
        'reason' => 'Manual count correction',
    ])->assertStatus(403);
});

test('store movement returns 404 for cross-organization and inactive products', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $user = createInventoryApiUserWithRole($organization->id, 'Inventory Manager');

    $otherProduct = Product::factory()->create([
        'organization_id' => $otherOrganization->id,
        'is_active' => true,
    ]);
    InventoryStock::factory()->create([
        'organization_id' => $otherOrganization->id,
        'product_id' => $otherProduct->id,
    ]);

    $inactiveProduct = Product::factory()->create([
        'organization_id' => $organization->id,
        'is_active' => false,
    ]);
    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $inactiveProduct->id,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/inventory/movements', [
        'product_id' => $otherProduct->id,
        'type' => 'restock',
        'quantity_delta' => 2,
        'reason' => 'Manual restock',
    ])->assertStatus(404);

    $this->postJson('/api/inventory/movements', [
        'product_id' => $inactiveProduct->id,
        'type' => 'restock',
        'quantity_delta' => 2,
        'reason' => 'Manual restock',
    ])->assertStatus(404);
});

test('store movement creates movement and returns movement with updated stock', function () {
    $organization = Organization::factory()->create();
    $user = createInventoryApiUserWithRole($organization->id, 'Inventory Manager');
    $product = Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'SKU-10',
        'name' => 'Stock Product',
        'is_active' => true,
    ]);
    $stock = InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_on_hand' => 10,
        'qty_reserved' => 2,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/inventory/movements', [
        'product_id' => $product->id,
        'type' => 'adjustment',
        'quantity_delta' => -3,
        'reason' => 'Manual recount',
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.movement.product_id', $product->id)
        ->assertJsonPath('data.movement.type', 'adjustment')
        ->assertJsonPath('data.movement.quantity_delta', -3)
        ->assertJsonPath('data.stock.product.id', $product->id)
        ->assertJsonPath('data.stock.qty_on_hand', 7)
        ->assertJsonPath('data.stock.qty_reserved', 2)
        ->assertJsonPath('data.stock.available', 5);

    $this->assertDatabaseHas('inventory_movements', [
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'performed_by_user_id' => $user->id,
        'type' => 'adjustment',
        'qty_delta' => -3,
        'reason' => 'Manual recount',
    ]);

    $this->assertDatabaseHas('inventory_stocks', [
        'id' => $stock->id,
        'qty_on_hand' => 7,
        'qty_reserved' => 2,
    ]);
});

test('store movement rejects forbidden types and invalid sign constraints', function () {
    $organization = Organization::factory()->create();
    $user = createInventoryApiUserWithRole($organization->id, 'Inventory Manager');
    $product = Product::factory()->create([
        'organization_id' => $organization->id,
        'is_active' => true,
    ]);
    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_on_hand' => 10,
        'qty_reserved' => 0,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/inventory/movements', [
        'product_id' => $product->id,
        'type' => 'sale',
        'quantity_delta' => -1,
        'reason' => 'Should not pass',
    ])->assertStatus(422)->assertJsonValidationErrors(['type']);

    $this->postJson('/api/inventory/movements', [
        'product_id' => $product->id,
        'type' => 'restock',
        'quantity_delta' => -1,
        'reason' => 'Bad sign',
    ])->assertStatus(422)->assertJsonValidationErrors(['quantity_delta']);

    $this->postJson('/api/inventory/movements', [
        'product_id' => $product->id,
        'type' => 'damage',
        'quantity_delta' => 1,
        'reason' => 'Bad sign',
    ])->assertStatus(422)->assertJsonValidationErrors(['quantity_delta']);
});

test('store movement returns 409 when movement would drop on hand below reserved', function () {
    $organization = Organization::factory()->create();
    $user = createInventoryApiUserWithRole($organization->id, 'Inventory Manager');
    $product = Product::factory()->create([
        'organization_id' => $organization->id,
        'is_active' => true,
    ]);
    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_on_hand' => 5,
        'qty_reserved' => 4,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/inventory/movements', [
        'product_id' => $product->id,
        'type' => 'adjustment',
        'quantity_delta' => -2,
        'reason' => 'Damaged pieces',
    ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'insufficient_stock');
});

function createInventoryApiUserWithRole(int $organizationId, string $role): User
{
    $user = User::factory()->create(['organization_id' => $organizationId]);
    $user->assignRole($role);

    return $user;
}
