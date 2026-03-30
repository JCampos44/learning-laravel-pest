<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('revokes the current token and returns 204', function () {
    $user = User::factory()->create();

    $plain = $user->createToken('phpunit')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/logout');

    $response->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});

it('returns 401 when no token provided', function () {
    $this->postJson('/api/v1/logout')->assertUnauthorized();
});

it('returns 401 for invalid token', function () {
    $this->withHeader('Authorization', 'Bearer invalid-token')
        ->postJson('/api/v1/logout')
        ->assertUnauthorized();
});
