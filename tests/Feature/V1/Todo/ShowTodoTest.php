<?php

use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function todoShowAuthHeader(User $user): array
{
    $plain = $user->createToken('phpunit')->plainTextToken;

    return ['Authorization' => 'Bearer '.$plain];
}

it('returns 401 when no token is provided', function () {
    $todo = Todo::factory()->create();

    $this->getJson('/api/v1/todos/'.$todo->id)->assertUnauthorized();
});

it('returns the todo for its owner', function () {
    $owner = User::factory()->create();
    $todo = Todo::factory()->create(['user_id' => $owner->id, 'title' => 'Owner todo']);

    $this->withHeaders(todoShowAuthHeader($owner))
        ->getJson('/api/v1/todos/'.$todo->id)
        ->assertOk()
        ->assertJsonPath('data.id', $todo->id)
        ->assertJsonPath('data.title', 'Owner todo')
        ->assertJsonPath('data.user_id', $owner->id);
});

it('returns 403 when trying to view another users todo', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $todo = Todo::factory()->create(['user_id' => $owner->id]);

    $this->withHeaders(todoShowAuthHeader($intruder))
        ->getJson('/api/v1/todos/'.$todo->id)
        ->assertForbidden();
});

it('returns 404 when todo does not exist', function () {
    $user = User::factory()->create();

    $this->withHeaders(todoShowAuthHeader($user))
        ->getJson('/api/v1/todos/999999')
        ->assertNotFound();
});
