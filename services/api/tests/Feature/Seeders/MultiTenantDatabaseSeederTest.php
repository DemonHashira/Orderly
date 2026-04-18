<?php

use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('database seeder creates two populated demo organizations', function () {
    $this->seed(DatabaseSeeder::class);

    $otakuStore = Organization::query()->where('slug', 'otaku-store')->first();
    $gearHub = Organization::query()->where('slug', 'gear-hub')->first();

    expect($otakuStore)->not->toBeNull()
        ->and($gearHub)->not->toBeNull()
        ->and(User::query()->where('organization_id', $otakuStore->id)->count())->toBeGreaterThan(0)
        ->and(User::query()->where('organization_id', $gearHub->id)->count())->toBeGreaterThan(0)
        ->and(Customer::query()->where('organization_id', $otakuStore->id)->count())->toBeGreaterThan(0)
        ->and(Customer::query()->where('organization_id', $gearHub->id)->count())->toBeGreaterThan(0)
        ->and(Product::query()->where('organization_id', $otakuStore->id)->count())->toBeGreaterThan(0)
        ->and(Product::query()->where('organization_id', $gearHub->id)->count())->toBeGreaterThan(0)
        ->and(InventoryStock::query()->where('organization_id', $otakuStore->id)->count())->toBeGreaterThan(0)
        ->and(InventoryStock::query()->where('organization_id', $gearHub->id)->count())->toBeGreaterThan(0)
        ->and(Order::query()->where('organization_id', $otakuStore->id)->count())->toBeGreaterThan(0)
        ->and(Order::query()->where('organization_id', $gearHub->id)->count())->toBeGreaterThan(0)
        ->and(
            Product::query()
                ->where('organization_id', $gearHub->id)
                ->where('sku', 'like', 'GH-%')
                ->exists(),
        )->toBeTrue()
        ->and(
            Order::query()
                ->where('organization_id', $gearHub->id)
                ->where('reference', 'like', 'GH-2026-%')
                ->exists(),
        )->toBeTrue();

});

test('seeded tenant owners only see their own products orders and team members', function () {
    $this->seed(DatabaseSeeder::class);

    $otakuOwner = User::query()->where('email', 'vlogodazhki@otakustore.test')->firstOrFail();
    $gearHubOwner = User::query()->where('email', 'owner@gearhub.test')->firstOrFail();

    Sanctum::actingAs($otakuOwner);

    $this->getJson('/api/products?per_page=100')
        ->assertSuccessful()
        ->assertJsonMissing(['sku' => 'GH-HEADSET-001']);

    $this->getJson('/api/orders?per_page=100')
        ->assertSuccessful()
        ->assertJsonMissing(['reference' => 'GH-2026-0001']);

    $this->getJson('/api/admin/users?per_page=100')
        ->assertSuccessful()
        ->assertJsonMissing(['email' => 'owner@gearhub.test']);

    Sanctum::actingAs($gearHubOwner);

    $this->getJson('/api/products?per_page=100')
        ->assertSuccessful()
        ->assertJsonFragment(['sku' => 'GH-HEADSET-001'])
        ->assertJsonMissing(['sku' => 'MANGA-JJK-001']);

    $this->getJson('/api/orders?per_page=25&q=GH-2026-0001')
        ->assertSuccessful()
        ->assertJsonFragment(['reference' => 'GH-2026-0001'])
        ->assertJsonMissing(['reference' => 'OC-2026-0001']);

    $this->getJson('/api/admin/users?per_page=100')
        ->assertSuccessful()
        ->assertJsonFragment(['email' => 'owner@gearhub.test'])
        ->assertJsonMissing(['email' => 'vlogodazhki@otakustore.test']);
});
