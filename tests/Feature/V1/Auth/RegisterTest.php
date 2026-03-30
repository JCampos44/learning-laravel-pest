<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('registers a new user and returns an access token', function () {
    $payload = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'phpunit',
    ];

    $response = $this->postJson('/api/v1/register', $payload);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'created_at', 'updated_at'],
            'meta' => ['access_token', 'token_type'],
        ]);

    $user = User::where('email', $payload['email'])->first();

    $this->assertModelExists($user);
    expect($response->json('meta.token_type'))->toBe('Bearer');
});

it('returns 422 for missing or invalid fields (dataset)', function (array $payload, array $expected) {
    $response = $this->postJson('/api/v1/register', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors($expected);
})->with([
    'missing_name' => [
        [
            'email' => 'a@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ],
        ['name'],
    ],
    'missing_email' => [
        [
            'name' => 'No Email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ],
        ['email'],
    ],
    'missing_password' => [
        [
            'name' => 'No Password',
            'email' => 'nopass@example.com',
            'password_confirmation' => 'password123',
        ],
        ['password'],
    ],
    'missing_password_confirmation' => [
        [
            'name' => 'No Confirm',
            'email' => 'noconfirm@example.com',
            'password' => 'password123',
        ],
        ['password_confirmation'],
    ],
    'invalid_email_format' => [
        [
            'name' => 'Bad Email',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ],
        ['email'],
    ],
    'short_password' => [
        [
            'name' => 'Short Pass',
            'email' => 'short@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ],
        ['password'],
    ],
]);

it('returns 422 when email is already taken', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $payload = [
        'name' => 'Duplicate',
        'email' => 'existing@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $this->postJson('/api/v1/register', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when password confirmation does not match', function () {
    $payload = [
        'name' => 'Mismatch',
        'email' => 'mismatch@example.com',
        'password' => 'password123',
        'password_confirmation' => 'different',
    ];

    $this->postJson('/api/v1/register', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password_confirmation']);
});
