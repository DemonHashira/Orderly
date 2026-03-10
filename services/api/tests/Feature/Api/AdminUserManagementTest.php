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

test('users manage endpoint requires users.manage permission', function () {
    $organization = Organization::factory()->create();
    $user = createAdminUserWithRole($organization->id, 'Order Manager');

    Sanctum::actingAs($user);

    $this->getJson('/api/admin/users')->assertStatus(403);
});

test('owner can list, create, update, and activate or deactivate users in same organization', function () {
    $organization = Organization::factory()->create();
    $owner = createAdminUserWithRole($organization->id, 'Owner');
    $member = createAdminUserWithRole($organization->id, 'Order Manager');

    Sanctum::actingAs($owner);

    $this->getJson('/api/admin/users?per_page=20')
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 2);

    $createResponse = $this->postJson('/api/admin/users', [
        'first_name' => 'Team',
        'middle_name' => null,
        'last_name' => 'Member',
        'email' => 'team.member@example.com',
        'password' => 'VeryStrong#A92!kLm5',
        'password_confirmation' => 'VeryStrong#A92!kLm5',
        'is_active' => true,
    ])->assertStatus(201);

    $createdUserId = (int) $createResponse->json('data.id');

    $this->patchJson('/api/admin/users/'.$createdUserId, [
        'first_name' => 'TeamUpdated',
        'last_name' => 'MemberUpdated',
        'email' => 'team.updated@example.com',
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.first_name', 'TeamUpdated')
        ->assertJsonPath('data.email', 'team.updated@example.com');

    $this->patchJson('/api/admin/users/'.$member->id.'/status', [
        'is_active' => false,
    ])->assertStatus(200)
        ->assertJsonPath('data.is_active', false);

    $this->patchJson('/api/admin/users/'.$member->id.'/status', [
        'is_active' => true,
    ])->assertStatus(200)
        ->assertJsonPath('data.is_active', true);
});

test('owner can assign role during user creation', function () {
    $organization = Organization::factory()->create();
    $owner = createAdminUserWithRole($organization->id, 'Owner');

    Sanctum::actingAs($owner);

    $createResponse = $this->postJson('/api/admin/users', [
        'first_name' => 'Role',
        'middle_name' => null,
        'last_name' => 'Assignee',
        'email' => 'role.assignee@example.com',
        'password' => 'VeryStrong#A92!kLm5',
        'password_confirmation' => 'VeryStrong#A92!kLm5',
        'role' => 'Logistics Manager',
    ])->assertStatus(201)
        ->assertJsonPath('data.role', 'Logistics Manager');

    $createdUserId = (int) $createResponse->json('data.id');

    $this->assertTrue(User::query()->findOrFail($createdUserId)->hasRole('Logistics Manager'));
});

test('create user with role requires roles.manage permission', function () {
    $organization = Organization::factory()->create();
    $actor = createAdminUserWithRole($organization->id, 'Order Manager');
    $actor->givePermissionTo('users.manage');

    Sanctum::actingAs($actor);

    $this->postJson('/api/admin/users', [
        'first_name' => 'No',
        'middle_name' => null,
        'last_name' => 'RolePermission',
        'email' => 'no.role.permission@example.com',
        'password' => 'VeryStrong#A92!kLm5',
        'password_confirmation' => 'VeryStrong#A92!kLm5',
        'role' => 'Inventory Manager',
    ])->assertStatus(403);
});

test('owner cannot update users from a different organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $owner = createAdminUserWithRole($organization->id, 'Owner');
    $otherUser = createAdminUserWithRole($otherOrganization->id, 'Order Manager');

    Sanctum::actingAs($owner);

    $this->patchJson('/api/admin/users/'.$otherUser->id, [
        'first_name' => 'Blocked',
    ])->assertStatus(404);
});

test('owner cannot deactivate own account', function () {
    $organization = Organization::factory()->create();
    $owner = createAdminUserWithRole($organization->id, 'Owner');

    Sanctum::actingAs($owner);

    $this->patchJson('/api/admin/users/'.$owner->id.'/status', [
        'is_active' => false,
    ])->assertStatus(422)
        ->assertJsonPath('message', 'You cannot deactivate your own account.');
});

test('owner cannot deactivate last active owner in organization', function () {
    $organization = Organization::factory()->create();
    $owner = createAdminUserWithRole($organization->id, 'Owner');
    $anotherUser = createAdminUserWithRole($organization->id, 'Order Manager');

    Sanctum::actingAs($anotherUser);

    $this->patchJson('/api/admin/users/'.$owner->id.'/status', [
        'is_active' => false,
    ])->assertStatus(403);

    Sanctum::actingAs($owner);

    $this->patchJson('/api/admin/users/'.$owner->id.'/status', [
        'is_active' => false,
    ])->assertStatus(422)
        ->assertJsonPath('message', 'You cannot deactivate your own account.');
});

function createAdminUserWithRole(int $organizationId, string $role): User
{
    $user = User::factory()->create([
        'organization_id' => $organizationId,
    ]);

    $user->assignRole($role);

    return $user;
}
