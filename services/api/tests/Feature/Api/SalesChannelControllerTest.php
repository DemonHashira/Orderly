<?php

use App\Models\Organization;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('authenticated user can list sales channels without pagination envelope', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $matching = SalesChannel::factory()->create([
        'name' => 'Instagram DM',
        'code' => 'instagram',
    ]);

    SalesChannel::factory()->create([
        'name' => 'Phone Order',
        'code' => 'phone',
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/sales-channels?q=insta');

    $response
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matching->id)
        ->assertJsonStructure([
            'data' => [[
                'id',
                'code',
                'name',
            ]],
        ])
        ->assertJsonMissingPath('links')
        ->assertJsonMissingPath('meta');
});

test('authenticated user can show sales channel', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $channel = SalesChannel::factory()->create([
        'name' => 'Facebook Marketplace',
        'code' => 'marketplace',
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/sales-channels/'.$channel->id)
        ->assertStatus(200)
        ->assertJsonPath('data.id', $channel->id)
        ->assertJsonPath('data.code', 'marketplace');
});

test('show missing sales channel returns 404', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    Sanctum::actingAs($user);

    $this->getJson('/api/sales-channels/999999')->assertStatus(404);
});

test('sales channels endpoints require authentication', function () {
    $channel = SalesChannel::factory()->create();

    $this->getJson('/api/sales-channels')->assertStatus(401);
    $this->getJson('/api/sales-channels/'.$channel->id)->assertStatus(401);
});
