<?php

use App\Models\Customer;
use App\Models\CustomerAddress;
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
        'address' => [
            'country' => 'Bulgaria',
            'city' => 'Sofia',
            'postal_code' => '1000',
            'address_line1' => 'Tsar Osvoboditel 1',
            'address_line2' => 'Floor 2',
        ],
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
        'address' => [
            'country' => 'Bulgaria',
            'city' => 'Sofia',
            'postal_code' => '1000',
            'address_line1' => 'Tsar Osvoboditel 1',
            'address_line2' => null,
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

test('create customer rejects required contact fields when they contain only whitespace', function () {
    $organization = Organization::factory()->create();
    $user = createUserWithRole($organization->id, 'Order Manager');

    Sanctum::actingAs($user);

    $this->postJson('/api/customers', [
        'first_name' => '   ',
        'middle_name' => '   ',
        'last_name' => '   ',
        'phone' => '   ',
        'email' => '   ',
        'address' => [
            'country' => 'Bulgaria',
            'city' => 'Sofia',
            'postal_code' => '1000',
            'address_line1' => 'Tsar Osvoboditel 1',
            'address_line2' => 'Floor 2',
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['first_name', 'last_name', 'phone', 'email']);
});

test('create customer rejects phone with unsupported characters', function () {
    $organization = Organization::factory()->create();
    $user = createUserWithRole($organization->id, 'Order Manager');

    Sanctum::actingAs($user);

    $this->postJson('/api/customers', [
        'first_name' => 'Invalid',
        'middle_name' => null,
        'last_name' => 'Phone',
        'phone' => '+359 888 123 456#',
        'email' => 'invalid-phone@example.com',
        'address' => [
            'country' => 'Bulgaria',
            'city' => 'Sofia',
            'postal_code' => '1000',
            'address_line1' => 'Tsar Osvoboditel 1',
            'address_line2' => null,
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone'])
        ->assertJsonPath(
            'errors.phone.0',
            'Phone may only contain digits, spaces, plus signs, hyphens, and parentheses.',
        );
});

test('create customer rejects phone with fewer than seven digits', function () {
    $organization = Organization::factory()->create();
    $user = createUserWithRole($organization->id, 'Order Manager');

    Sanctum::actingAs($user);

    $this->postJson('/api/customers', [
        'first_name' => 'Short',
        'middle_name' => null,
        'last_name' => 'Phone',
        'phone' => '+12 34',
        'email' => 'short-phone@example.com',
        'address' => [
            'country' => 'Bulgaria',
            'city' => 'Sofia',
            'postal_code' => '1000',
            'address_line1' => 'Tsar Osvoboditel 1',
            'address_line2' => null,
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone'])
        ->assertJsonPath('errors.phone.0', 'Phone must contain at least 7 digits.');
});

test('create customer normalizes email before storing', function () {
    $organization = Organization::factory()->create();
    $user = createUserWithRole($organization->id, 'Order Manager');

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/customers', [
        'first_name' => '  Mira  ',
        'middle_name' => '   ',
        'last_name' => '  Stone ',
        'phone' => '  +359 123 123 123  ',
        'email' => '  MIRA@EXAMPLE.COM  ',
        'address' => [
            'country' => ' Bulgaria ',
            'city' => ' Sofia ',
            'postal_code' => ' 1000 ',
            'address_line1' => ' Tsar Osvoboditel 1 ',
            'address_line2' => ' Floor 2 ',
        ],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.email', 'mira@example.com')
        ->assertJsonPath('data.first_name', 'Mira')
        ->assertJsonPath('data.middle_name', null)
        ->assertJsonPath('data.last_name', 'Stone')
        ->assertJsonPath('data.phone', '+359 123 123 123');

    $this->assertDatabaseHas('customers', [
        'organization_id' => $organization->id,
        'first_name' => 'Mira',
        'middle_name' => null,
        'last_name' => 'Stone',
        'phone' => '+359 123 123 123',
        'email' => 'mira@example.com',
    ]);

    $this->assertDatabaseHas('customer_addresses', [
        'customer_id' => $response->json('data.id'),
        'country' => 'Bulgaria',
        'city' => 'Sofia',
        'postal_code' => '1000',
        'address_line1' => 'Tsar Osvoboditel 1',
        'address_line2' => 'Floor 2',
        'is_default' => true,
    ]);
});

test('create customer requires a full address', function () {
    $organization = Organization::factory()->create();
    $user = createUserWithRole($organization->id, 'Order Manager');

    Sanctum::actingAs($user);

    $this->postJson('/api/customers', [
        'first_name' => 'Mira',
        'middle_name' => null,
        'last_name' => 'Stone',
        'phone' => '+359123123123',
        'email' => 'mira@example.com',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'address.country',
            'address.city',
            'address.postal_code',
            'address.address_line1',
        ]);
});

test('create customer with partial address requires the missing address fields', function () {
    $organization = Organization::factory()->create();
    $user = createUserWithRole($organization->id, 'Order Manager');

    Sanctum::actingAs($user);

    $this->postJson('/api/customers', [
        'first_name' => 'Mira',
        'middle_name' => null,
        'last_name' => 'Stone',
        'phone' => '+359123123123',
        'email' => 'mira@example.com',
        'address' => [
            'country' => 'Bulgaria',
            'city' => 'Sofia',
            'postal_code' => '',
            'address_line1' => '',
            'address_line2' => '',
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['address.postal_code', 'address.address_line1']);
});

test('create customer with full address stores a default customer address', function () {
    $organization = Organization::factory()->create();
    $user = createUserWithRole($organization->id, 'Order Manager');

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/customers', [
        'first_name' => 'Mira',
        'middle_name' => null,
        'last_name' => 'Stone',
        'phone' => '+359123123123',
        'email' => 'mira@example.com',
        'address' => [
            'country' => 'Bulgaria',
            'city' => 'Sofia',
            'postal_code' => '1000',
            'address_line1' => 'Tsar Osvoboditel 1',
            'address_line2' => 'Floor 2',
        ],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.address.country', 'Bulgaria')
        ->assertJsonPath('data.address.city', 'Sofia')
        ->assertJsonPath('data.address.postal_code', '1000')
        ->assertJsonPath('data.address.address_line1', 'Tsar Osvoboditel 1')
        ->assertJsonPath('data.address.address_line2', 'Floor 2');

    $customerId = $response->json('data.id');

    $this->assertDatabaseHas('customer_addresses', [
        'customer_id' => $customerId,
        'country' => 'Bulgaria',
        'city' => 'Sofia',
        'postal_code' => '1000',
        'address_line1' => 'Tsar Osvoboditel 1',
        'address_line2' => 'Floor 2',
        'is_default' => true,
    ]);
});

test('create customer treats trimmed and lowercased email as duplicate within the same organization', function () {
    $organization = Organization::factory()->create();

    Customer::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'duplicate@example.com',
    ]);

    $user = createUserWithRole($organization->id, 'Order Manager');

    Sanctum::actingAs($user);

    $this->postJson('/api/customers', [
        'first_name' => 'Duplicate',
        'middle_name' => null,
        'last_name' => 'Email',
        'phone' => '+359555666777',
        'email' => '  DUPLICATE@EXAMPLE.COM  ',
        'address' => [
            'country' => 'Bulgaria',
            'city' => 'Sofia',
            'postal_code' => '1000',
            'address_line1' => 'Tsar Osvoboditel 1',
            'address_line2' => null,
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
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
        'address' => [
            'country' => 'Bulgaria',
            'city' => 'Sofia',
            'postal_code' => '1000',
            'address_line1' => 'Tsar Osvoboditel 1',
            'address_line2' => null,
        ],
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

test('update customer normalizes trimmed inputs before saving', function () {
    $organization = Organization::factory()->create();

    $user = createUserWithRole($organization->id, 'Order Manager');
    $customer = Customer::factory()->create([
        'organization_id' => $organization->id,
        'first_name' => 'Before',
        'middle_name' => 'Middle',
        'last_name' => 'Name',
        'phone' => '+359200300400',
        'email' => 'before@example.com',
    ]);

    Sanctum::actingAs($user);

    $this->putJson('/api/customers/'.$customer->id, [
        'first_name' => '  After  ',
        'middle_name' => '   ',
        'last_name' => '  Name ',
        'phone' => '  +359 200 300 401  ',
        'email' => '  AFTER@EXAMPLE.COM  ',
        'address' => [
            'country' => ' Bulgaria ',
            'city' => ' Sofia ',
            'postal_code' => ' 1000 ',
            'address_line1' => ' Tsar Osvoboditel 1 ',
            'address_line2' => ' Floor 2 ',
        ],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.first_name', 'After')
        ->assertJsonPath('data.middle_name', null)
        ->assertJsonPath('data.last_name', 'Name')
        ->assertJsonPath('data.phone', '+359 200 300 401')
        ->assertJsonPath('data.email', 'after@example.com');

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'first_name' => 'After',
        'middle_name' => null,
        'last_name' => 'Name',
        'phone' => '+359 200 300 401',
        'email' => 'after@example.com',
    ]);

    $this->assertDatabaseHas('customer_addresses', [
        'customer_id' => $customer->id,
        'country' => 'Bulgaria',
        'city' => 'Sofia',
        'postal_code' => '1000',
        'address_line1' => 'Tsar Osvoboditel 1',
        'address_line2' => 'Floor 2',
        'is_default' => true,
    ]);
});

test('update customer creates a default address when it was previously missing', function () {
    $organization = Organization::factory()->create();

    $user = createUserWithRole($organization->id, 'Order Manager');
    $customer = Customer::factory()->create([
        'organization_id' => $organization->id,
    ]);

    Sanctum::actingAs($user);

    $this->putJson('/api/customers/'.$customer->id, [
        'first_name' => $customer->first_name,
        'middle_name' => $customer->middle_name,
        'last_name' => $customer->last_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
        'address' => [
            'country' => 'Bulgaria',
            'city' => 'Varna',
            'postal_code' => '9000',
            'address_line1' => 'Primorski 12',
            'address_line2' => '',
        ],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.address.city', 'Varna');

    $this->assertDatabaseHas('customer_addresses', [
        'customer_id' => $customer->id,
        'country' => 'Bulgaria',
        'city' => 'Varna',
        'postal_code' => '9000',
        'address_line1' => 'Primorski 12',
        'address_line2' => null,
        'is_default' => true,
    ]);
});

test('update customer edits the existing default address', function () {
    $organization = Organization::factory()->create();

    $user = createUserWithRole($organization->id, 'Order Manager');
    $customer = Customer::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $address = CustomerAddress::factory()->default()->create([
        'customer_id' => $customer->id,
        'country' => 'Bulgaria',
        'city' => 'Sofia',
        'postal_code' => '1000',
        'address_line1' => 'Old Address',
        'address_line2' => 'Old Line 2',
    ]);

    Sanctum::actingAs($user);

    $this->putJson('/api/customers/'.$customer->id, [
        'first_name' => $customer->first_name,
        'middle_name' => $customer->middle_name,
        'last_name' => $customer->last_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
        'address' => [
            'country' => 'Romania',
            'city' => 'Bucharest',
            'postal_code' => '010101',
            'address_line1' => 'New Address',
            'address_line2' => 'Suite 5',
        ],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.address.country', 'Romania')
        ->assertJsonPath('data.address.city', 'Bucharest');

    $this->assertDatabaseHas('customer_addresses', [
        'id' => $address->id,
        'customer_id' => $customer->id,
        'country' => 'Romania',
        'city' => 'Bucharest',
        'postal_code' => '010101',
        'address_line1' => 'New Address',
        'address_line2' => 'Suite 5',
        'is_default' => true,
    ]);
});

test('update customer requires the address when the default address is cleared', function () {
    $organization = Organization::factory()->create();

    $user = createUserWithRole($organization->id, 'Order Manager');
    $customer = Customer::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $defaultAddress = CustomerAddress::factory()->default()->create([
        'customer_id' => $customer->id,
    ]);

    Sanctum::actingAs($user);

    $this->putJson('/api/customers/'.$customer->id, [
        'first_name' => $customer->first_name,
        'middle_name' => $customer->middle_name,
        'last_name' => $customer->last_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
        'address' => [
            'country' => '',
            'city' => '',
            'postal_code' => '',
            'address_line1' => '',
            'address_line2' => '',
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'address.country',
            'address.city',
            'address.postal_code',
            'address.address_line1',
        ]);

    $this->assertDatabaseHas('customer_addresses', [
        'id' => $defaultAddress->id,
    ]);
});

test('show returns the customer address when present', function () {
    $organization = Organization::factory()->create();

    $user = createUserWithRole($organization->id, 'Owner');
    $customer = Customer::factory()->create([
        'organization_id' => $organization->id,
    ]);
    CustomerAddress::factory()->default()->create([
        'customer_id' => $customer->id,
        'country' => 'Bulgaria',
        'city' => 'Plovdiv',
        'postal_code' => '4000',
        'address_line1' => 'Main 5',
        'address_line2' => null,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/customers/'.$customer->id)
        ->assertSuccessful()
        ->assertJsonPath('data.address.country', 'Bulgaria')
        ->assertJsonPath('data.address.city', 'Plovdiv')
        ->assertJsonPath('data.address.postal_code', '4000')
        ->assertJsonPath('data.address.address_line1', 'Main 5')
        ->assertJsonPath('data.address.address_line2', null);
});

test('update customer rejects phone with fewer than seven digits', function () {
    $organization = Organization::factory()->create();

    $user = createUserWithRole($organization->id, 'Order Manager');
    $customer = Customer::factory()->create([
        'organization_id' => $organization->id,
    ]);

    Sanctum::actingAs($user);

    $this->putJson('/api/customers/'.$customer->id, [
        'first_name' => $customer->first_name,
        'middle_name' => $customer->middle_name,
        'last_name' => $customer->last_name,
        'phone' => '+12 34',
        'email' => $customer->email,
        'address' => [
            'country' => 'Bulgaria',
            'city' => 'Sofia',
            'postal_code' => '1000',
            'address_line1' => 'Tsar Osvoboditel 1',
            'address_line2' => null,
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone'])
        ->assertJsonPath('errors.phone.0', 'Phone must contain at least 7 digits.');
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
        'address' => [
            'country' => 'Bulgaria',
            'city' => 'Sofia',
            'postal_code' => '1000',
            'address_line1' => 'Tsar Osvoboditel 1',
            'address_line2' => null,
        ],
    ])->assertStatus(403);

    $this->putJson('/api/customers/'.$customer->id, [
        'first_name' => 'Nope',
        'middle_name' => null,
        'last_name' => 'Update',
        'phone' => '+359100200301',
        'email' => 'nope-update@example.com',
        'address' => [
            'country' => 'Bulgaria',
            'city' => 'Sofia',
            'postal_code' => '1000',
            'address_line1' => 'Tsar Osvoboditel 1',
            'address_line2' => null,
        ],
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
        'address' => [
            'country' => 'Bulgaria',
            'city' => 'Sofia',
            'postal_code' => '1000',
            'address_line1' => 'Tsar Osvoboditel 1',
            'address_line2' => null,
        ],
    ])->assertStatus(403);
    $this->putJson('/api/customers/'.$customer->id, [
        'first_name' => 'Nope',
        'middle_name' => null,
        'last_name' => 'Update',
        'phone' => '+359333444556',
        'email' => 'inventory-update@example.com',
        'address' => [
            'country' => 'Bulgaria',
            'city' => 'Sofia',
            'postal_code' => '1000',
            'address_line1' => 'Tsar Osvoboditel 1',
            'address_line2' => null,
        ],
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
        'address' => [
            'country' => 'Bulgaria',
            'city' => 'Sofia',
            'postal_code' => '1000',
            'address_line1' => 'Tsar Osvoboditel 1',
            'address_line2' => null,
        ],
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
        'address' => [
            'country' => 'Bulgaria',
            'city' => 'Sofia',
            'postal_code' => '1000',
            'address_line1' => 'Tsar Osvoboditel 1',
            'address_line2' => null,
        ],
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
        'address' => [
            'country' => 'Bulgaria',
            'city' => 'Sofia',
            'postal_code' => '1000',
            'address_line1' => 'Tsar Osvoboditel 1',
            'address_line2' => null,
        ],
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
