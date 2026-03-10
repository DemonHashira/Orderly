<?php

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

test('roles endpoint requires roles.manage permission', function () {
    $organization = Organization::factory()->create();
    $user = createRoleAdminUserWithRole($organization->id, 'Order Manager');

    Sanctum::actingAs($user);

    $this->getJson('/api/admin/roles')->assertStatus(403);
});

test('owner can list assignable roles', function () {
    $organization = Organization::factory()->create();
    $owner = createRoleAdminUserWithRole($organization->id, 'Owner');

    Sanctum::actingAs($owner);

    $this->getJson('/api/admin/roles')
        ->assertStatus(200)
        ->assertJsonPath('data.0.name', 'Inventory Manager')
        ->assertJsonFragment(['name' => 'Owner']);
});

test('owner can assign role to user in same organization', function () {
    $organization = Organization::factory()->create();
    $owner = createRoleAdminUserWithRole($organization->id, 'Owner');
    $user = createRoleAdminUserWithRole($organization->id, 'Order Manager');

    Sanctum::actingAs($owner);

    $this->patchJson('/api/admin/users/'.$user->id.'/role', [
        'role' => 'Logistics Manager',
    ])->assertStatus(200)
        ->assertJsonPath('data.role', 'Logistics Manager');

    $this->assertTrue($user->fresh()->hasRole('Logistics Manager'));
});

test('owner cannot assign role to user from another organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $owner = createRoleAdminUserWithRole($organization->id, 'Owner');
    $user = createRoleAdminUserWithRole($otherOrganization->id, 'Order Manager');

    Sanctum::actingAs($owner);

    $this->patchJson('/api/admin/users/'.$user->id.'/role', [
        'role' => 'Inventory Manager',
    ])->assertStatus(404);
});

test('role assignment requires roles.manage permission', function () {
    $organization = Organization::factory()->create();
    $actor = createRoleAdminUserWithRole($organization->id, 'Order Manager');
    $user = createRoleAdminUserWithRole($organization->id, 'Logistics Manager');

    Sanctum::actingAs($actor);

    $this->patchJson('/api/admin/users/'.$user->id.'/role', [
        'role' => 'Inventory Manager',
    ])->assertStatus(403);
});

test('cannot remove owner role from last active owner', function () {
    $organization = Organization::factory()->create();
    $owner = createRoleAdminUserWithRole($organization->id, 'Owner');

    Sanctum::actingAs($owner);

    $this->patchJson('/api/admin/users/'.$owner->id.'/role', [
        'role' => 'Order Manager',
    ])->assertStatus(422)
        ->assertJsonPath('message', 'You cannot remove Owner role from the last active Owner.');
});

test('can change own owner role when another active owner exists', function () {
    $organization = Organization::factory()->create();
    $ownerA = createRoleAdminUserWithRole($organization->id, 'Owner');
    $ownerB = createRoleAdminUserWithRole($organization->id, 'Owner');

    Sanctum::actingAs($ownerA);

    $this->patchJson('/api/admin/users/'.$ownerA->id.'/role', [
        'role' => 'Order Manager',
    ])->assertStatus(200)
        ->assertJsonPath('data.role', 'Order Manager');
});

test('role assignment validates allowed role values', function () {
    $organization = Organization::factory()->create();
    $owner = createRoleAdminUserWithRole($organization->id, 'Owner');
    $user = createRoleAdminUserWithRole($organization->id, 'Order Manager');

    Sanctum::actingAs($owner);

    $this->patchJson('/api/admin/users/'.$user->id.'/role', [
        'role' => 'Supervisor',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('role');
});

function createRoleAdminUserWithRole(int $organizationId, string $role): User
{
    $user = User::factory()->create([
        'organization_id' => $organizationId,
    ]);

    $user->assignRole($role);

    return $user;
}
