<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('login returns authenticated user and session cookie', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'email' => 'viktor@example.com',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertStatus(200)
        ->assertJsonStructure([
            'user' => [
                'id',
                'organization_id',
                'email',
                'first_name',
                'middle_name',
                'last_name',
                'is_active',
                'created_at',
                'updated_at',
            ],
        ])
        ->assertJsonMissingPath('token')
        ->assertCookie(config('session.cookie'));
});

test('login with remember sets recaller cookie', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'email' => 'remember@example.com',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'remember' => true,
    ]);

    $response
        ->assertStatus(200)
        ->assertCookie(Auth::guard('web')->getRecallerName());
});

test('login rejects invalid credentials', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'email' => 'viktor@example.com',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

test('login rejects inactive user', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'email' => 'inactive@example.com',
        'is_active' => false,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

test('me returns user roles and permissions', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
    ]);

    $this->actingAs($user, 'web');

    $response = $this->getJson('/api/auth/me');

    $response
        ->assertStatus(200)
        ->assertJsonStructure([
            'user' => [
                'id',
                'organization_id',
                'email',
                'first_name',
                'middle_name',
                'last_name',
                'is_active',
                'created_at',
                'updated_at',
            ],
            'roles',
            'permissions',
        ]);
});

test('logout invalidates session authentication', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'email' => 'logout@example.com',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertStatus(200);

    $response = $this->postJson('/api/auth/logout');

    $response
        ->assertStatus(200)
        ->assertJson([
            'message' => 'Logged out.',
        ]);

    $this->assertGuest('web');
});

test('login is rate limited', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'email' => 'throttle@example.com',
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(429);
});

test('token login returns token and user', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'email' => 'token@example.com',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/auth/token', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'postman',
    ]);

    $response
        ->assertStatus(200)
        ->assertJsonStructure([
            'token',
            'user' => [
                'id',
                'organization_id',
                'email',
                'first_name',
                'middle_name',
                'last_name',
                'is_active',
                'created_at',
                'updated_at',
            ],
        ]);
});

test('token logout revokes current token', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
    ]);

    $token = $user->createToken('postman');
    $tokenId = $token->accessToken->id;

    $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
        ->postJson('/api/auth/token/logout');

    $response
        ->assertStatus(200)
        ->assertJson([
            'message' => 'Logged out.',
        ]);

    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $tokenId,
    ]);
});

test('authenticated user can change password', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'password' => 'old-password',
    ]);

    $this->actingAs($user, 'web');

    $this->postJson('/api/auth/change-password', [
        'current_password' => 'old-password',
        'password' => 'New-password-123!',
        'password_confirmation' => 'New-password-123!',
    ])
        ->assertStatus(200)
        ->assertJson(['message' => 'Password changed successfully.']);

    $user->refresh();
    expect(Hash::check('New-password-123!', $user->password))->toBeTrue();
});

test('change password rejects invalid current password', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'password' => 'old-password',
    ]);

    $this->actingAs($user, 'web');

    $this->postJson('/api/auth/change-password', [
        'current_password' => 'wrong-password',
        'password' => 'New-password-123!',
        'password_confirmation' => 'New-password-123!',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('current_password');
});

test('change password rejects same password', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'password' => 'old-password',
    ]);

    $this->actingAs($user, 'web');

    $this->postJson('/api/auth/change-password', [
        'current_password' => 'old-password',
        'password' => 'old-password',
        'password_confirmation' => 'old-password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

test('change password enforces strong password rules', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'password' => 'old-password',
    ]);

    $this->actingAs($user, 'web');

    $this->postJson('/api/auth/change-password', [
        'current_password' => 'old-password',
        'password' => 'weakpass1',
        'password_confirmation' => 'weakpass1',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');
});
