<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('logs in with valid credentials and returns an access token', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password123',
        'device_name' => 'phpunit',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'created_at', 'updated_at'],
            'meta' => ['access_token', 'token_type'],
        ]);

    expect($response->json('meta.token_type'))->toBe('Bearer')
        ->and($response->json('data.id'))->toBe($user->id);
});

it('returns 401 with wrong password', function () {
    $user = User::factory()->create([
        'password' => bcrypt('correct-password'),
    ]);

    $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])
        ->assertUnauthorized()
        ->assertJson(['message' => 'The provided credentials are incorrect.']);
});

it('returns 401 for non-existent email', function () {
    $this->postJson('/api/v1/login', [
        'email' => 'ghost@example.com',
        'password' => 'password123',
    ])
        ->assertUnauthorized()
        ->assertJson(['message' => 'The provided credentials are incorrect.']);
});

it('returns 403 when the user has not verified their email', function () {
    $user = User::factory()->unverified()->create([
        'password' => bcrypt('password123'),
    ]);

    $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])
        ->assertForbidden()
        ->assertJson(['message' => 'Please verify your email address.']);
});

it('returns 422 for missing or invalid fields (dataset)', function (array $payload, array $expected) {
    $this->postJson('/api/v1/login', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors($expected);
})->with([
    'missing_email' => [
        ['password' => 'password123'],
        ['email'],
    ],
    'missing_password' => [
        ['email' => 'user@example.com'],
        ['password'],
    ],
    'invalid_email_format' => [
        ['email' => 'not-an-email', 'password' => 'password123'],
        ['email'],
    ],
]);
