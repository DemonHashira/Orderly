<?php

use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

test('index returns paginated customers scoped to authenticated organization and supports search', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $user = createUserWithRole($organization->id, 'Logistics Manager');

    $matchingCustomer = Customer::factory()->create([
        'organization_id' => $organization->id,
        'first_name' => 'Alice',
        'last_name' => 'Tester',
        'email' => 'alice@example.com',
        'phone' => '+359111111111',
    ]);

    Customer::factory()->create([
        'organization_id' => $organization->id,
        'first_name' => 'Bob',
        'email' => 'bob@example.com',
    ]);

    Customer::factory()->create([
        'organization_id' => $otherOrganization->id,
        'first_name' => 'Alice',
        'email' => 'alice-other@example.com',
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/customers?q=ali&per_page=1');

    $response
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingCustomer->id)
        ->assertJsonStructure([
            'data' => [[
                'id',
                'name',
                'first_name',
                'middle_name',
                'last_name',
                'email',
                'phone',
            ]],
            'links',
            'meta',
        ]);
});

test('show returns 404 for cross organization customer', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $user = createUserWithRole($organization->id, 'Owner');
    $customer = Customer::factory()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/customers/'.$customer->id)
        ->assertStatus(404);
});

test('order manager can create customer and organization_id from payload is ignored', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $user = createUserWithRole($organization->id, 'Order Manager');

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/customers', [
        'organization_id' => $otherOrganization->id,
        'first_name' => 'Mira',
        'middle_name' => null,
        'last_name' => 'Stone',
        'phone' => '+359123123123',
        'email' => 'mira@example.com',
        'notes' => 'should be ignored',
    ]);

    $response
        ->assertStatus(201)
        ->assertJsonPath('data.first_name', 'Mira')
        ->assertJsonMissingPath('data.notes');

    $this->assertDatabaseHas('customers', [
        'first_name' => 'Mira',
        'organization_id' => $organization->id,
    ]);
});

test('create customer requires email', function () {
    $organization = Organization::factory()->create();
    $user = createUserWithRole($organization->id, 'Order Manager');

    Sanctum::actingAs($user);

    $this->postJson('/api/customers', [
        'first_name' => 'No',
        'middle_name' => null,
        'last_name' => 'Email',
        'phone' => '+359900100200',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

test('order manager can update customer in same organization', function () {
    $organization = Organization::factory()->create();

    $user = createUserWithRole($organization->id, 'Order Manager');
    $customer = Customer::factory()->create([
        'organization_id' => $organization->id,
        'first_name' => 'Before',
        'last_name' => 'Name',
        'phone' => '+359200300400',
        'email' => 'before@example.com',
    ]);

    Sanctum::actingAs($user);

    $this->putJson('/api/customers/'.$customer->id, [
        'first_name' => 'After',
        'middle_name' => 'Middle',
        'last_name' => 'Name',
        'phone' => '+359200300401',
        'email' => 'after@example.com',
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.first_name', 'After')
        ->assertJsonPath('data.email', 'after@example.com');

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'first_name' => 'After',
        'email' => 'after@example.com',
    ]);
});

test('logistics manager is read only for customers', function () {
    $organization = Organization::factory()->create();

    $user = createUserWithRole($organization->id, 'Logistics Manager');
    $customer = Customer::factory()->create([
        'organization_id' => $organization->id,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/customers')->assertStatus(200);
    $this->getJson('/api/customers/'.$customer->id)->assertStatus(200);

    $this->postJson('/api/customers', [
        'first_name' => 'Nope',
        'last_name' => 'Create',
        'phone' => '+359100200300',
        'email' => 'nope@example.com',
    ])->assertStatus(403);

    $this->putJson('/api/customers/'.$customer->id, [
        'first_name' => 'Nope',
        'middle_name' => null,
        'last_name' => 'Update',
        'phone' => '+359100200301',
        'email' => 'nope-update@example.com',
    ])->assertStatus(403);

    $this->deleteJson('/api/customers/'.$customer->id)->assertStatus(403);
});

test('inventory manager is denied from customer endpoints', function () {
    $organization = Organization::factory()->create();

    $user = createUserWithRole($organization->id, 'Inventory Manager');
    $customer = Customer::factory()->create([
        'organization_id' => $organization->id,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/customers')->assertStatus(403);
    $this->getJson('/api/customers/'.$customer->id)->assertStatus(403);
    $this->postJson('/api/customers', [
        'first_name' => 'Nope',
        'last_name' => 'Create',
        'phone' => '+359333444555',
        'email' => 'inventory-create@example.com',
    ])->assertStatus(403);
    $this->putJson('/api/customers/'.$customer->id, [
        'first_name' => 'Nope',
        'middle_name' => null,
        'last_name' => 'Update',
        'phone' => '+359333444556',
        'email' => 'inventory-update@example.com',
    ])->assertStatus(403);
    $this->deleteJson('/api/customers/'.$customer->id)->assertStatus(403);
});

test('customer update permission can view a same-organization customer for edit flows', function () {
    $organization = Organization::factory()->create();
    $customer = Customer::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $user = User::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $user->givePermissionTo(Permission::findByName('customers.update', 'web'));

    Sanctum::actingAs($user);

    $this->getJson('/api/customers/'.$customer->id)
        ->assertStatus(200)
        ->assertJsonPath('data.id', $customer->id);

    $this->getJson('/api/customers')->assertStatus(403);
});

test('email uniqueness is scoped per organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    Customer::factory()->create([
        'organization_id' => $otherOrganization->id,
        'email' => 'shared@example.com',
    ]);

    $user = createUserWithRole($organization->id, 'Order Manager');

    Sanctum::actingAs($user);

    $this->postJson('/api/customers', [
        'first_name' => 'Scoped',
        'last_name' => 'Email',
        'phone' => '+359555666777',
        'email' => 'shared@example.com',
    ])->assertStatus(201);
});

test('soft deleted customer email cannot be reused in same organization', function () {
    $organization = Organization::factory()->create();

    $customer = Customer::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'reusable@example.com',
    ]);
    $customer->delete();

    $user = createUserWithRole($organization->id, 'Order Manager');

    Sanctum::actingAs($user);

    $this->postJson('/api/customers', [
        'first_name' => 'Reuse',
        'last_name' => 'Email',
        'phone' => '+359777888999',
        'email' => 'reusable@example.com',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

test('update allows keeping same email on same customer', function () {
    $organization = Organization::factory()->create();

    $customer = Customer::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'same@example.com',
    ]);

    $user = createUserWithRole($organization->id, 'Order Manager');

    Sanctum::actingAs($user);

    $this->putJson('/api/customers/'.$customer->id, [
        'first_name' => $customer->first_name,
        'middle_name' => $customer->middle_name,
        'last_name' => $customer->last_name,
        'phone' => $customer->phone,
        'email' => 'same@example.com',
    ])->assertStatus(200);
});

test('owner can soft delete customer', function () {
    $organization = Organization::factory()->create();
    $user = createUserWithRole($organization->id, 'Owner');

    $customer = Customer::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'delete-me@example.com',
    ]);

    Sanctum::actingAs($user);

    $this->deleteJson('/api/customers/'.$customer->id)->assertStatus(204);

    $this->assertSoftDeleted('customers', [
        'id' => $customer->id,
    ]);
});

test('delete returns 404 for cross organization customer', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $user = createUserWithRole($organization->id, 'Owner');

    $customer = Customer::factory()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    Sanctum::actingAs($user);

    $this->deleteJson('/api/customers/'.$customer->id)->assertStatus(404);
});

function createUserWithRole(int $organizationId, string $role): User
{
    $user = User::factory()->create([
        'organization_id' => $organizationId,
    ]);

    $user->assignRole($role);

    return $user;
}
