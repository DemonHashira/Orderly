<?php

use App\Models\Organization;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

test('order create lookup endpoint requires authentication', function () {
    $this->getJson('/api/lookups/order-create')->assertStatus(401);
});

test('order create lookups returns active org products and all sales channels', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $user = createLookupUserWithRole($organization->id, 'Order Manager');

    $activeProduct = Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'LOOK-100',
        'name' => 'Active Product',
        'is_active' => true,
    ]);
    Product::factory()->create([
        'organization_id' => $organization->id,
        'sku' => 'LOOK-200',
        'name' => 'Inactive Product',
        'is_active' => false,
    ]);
    Product::factory()->create([
        'organization_id' => $otherOrganization->id,
        'sku' => 'LOOK-300',
        'name' => 'Other Org Product',
        'is_active' => true,
    ]);

    $channel = SalesChannel::factory()->create([
        'code' => 'instagram',
        'name' => 'Instagram',
    ]);
    SalesChannel::factory()->create([
        'code' => 'phone',
        'name' => 'Phone',
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/lookups/order-create')
        ->assertOk()
        ->assertJsonPath('data.products.0.id', $activeProduct->id)
        ->assertJsonFragment([
            'id' => $activeProduct->id,
            'sku' => 'LOOK-100',
            'name' => 'Active Product',
        ])
        ->assertJsonMissing([
            'sku' => 'LOOK-200',
        ])
        ->assertJsonMissing([
            'sku' => 'LOOK-300',
        ])
        ->assertJsonFragment([
            'id' => $channel->id,
            'code' => 'instagram',
            'name' => 'Instagram',
        ])
        ->assertJsonStructure([
            'data' => [
                'sales_channels' => [[
                    'id',
                    'code',
                    'name',
                ]],
                'products' => [[
                    'id',
                    'sku',
                    'name',
                ]],
            ],
        ]);
});

test('order create lookup endpoint requires orders create permission', function () {
    $organization = Organization::factory()->create();
    $user = createLookupUserWithRole($organization->id, 'Inventory Manager');

    Sanctum::actingAs($user);

    $this->getJson('/api/lookups/order-create')->assertStatus(403);
});

function createLookupUserWithRole(int $organizationId, string $role): User
{
    $user = User::factory()->create(['organization_id' => $organizationId]);
    $user->assignRole($role);

    return $user;
}
