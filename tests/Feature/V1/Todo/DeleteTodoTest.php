<?php

use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function todoDeleteAuthHeader(User $user): array
{
    $plain = $user->createToken('phpunit')->plainTextToken;

    return ['Authorization' => 'Bearer '.$plain];
}

it('returns 401 when no token is provided', function () {
    $todo = Todo::factory()->create();

    $this->deleteJson('/api/v1/todos/'.$todo->id)->assertUnauthorized();
});

it('soft deletes the todo for its owner and returns 204', function () {
    $owner = User::factory()->create();
    $todo = Todo::factory()->create(['user_id' => $owner->id]);

    $this->withHeaders(todoDeleteAuthHeader($owner))
        ->deleteJson('/api/v1/todos/'.$todo->id)
        ->assertNoContent();

    $this->assertSoftDeleted('todos', ['id' => $todo->id]);
});

it('returns 403 when trying to delete another users todo', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $todo = Todo::factory()->create(['user_id' => $owner->id]);

    $this->withHeaders(todoDeleteAuthHeader($intruder))
        ->deleteJson('/api/v1/todos/'.$todo->id)
        ->assertForbidden();
});

it('returns 404 when todo does not exist', function () {
    $user = User::factory()->create();

    $this->withHeaders(todoDeleteAuthHeader($user))
        ->deleteJson('/api/v1/todos/999999')
        ->assertNotFound();
});
