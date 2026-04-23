<?php

use App\Enums\V1\TodoStatus;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function todoUpdateAuthHeader(User $user): array
{
    $plain = $user->createToken('phpunit')->plainTextToken;

    return ['Authorization' => 'Bearer '.$plain];
}

it('returns 401 when no token is provided', function () {
    $todo = Todo::factory()->create();

    $this->patchJson('/api/v1/todos/'.$todo->id, [
        'title' => 'Updated',
    ])->assertUnauthorized();
});

it('updates only provided fields', function () {
    $user = User::factory()->create();
    $todo = Todo::factory()->pending()->create([
        'user_id' => $user->id,
        'title' => 'Old title',
        'description' => 'Old description',
    ]);

    $this->withHeaders(todoUpdateAuthHeader($user))
        ->patchJson('/api/v1/todos/'.$todo->id, [
            'title' => 'New title',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'New title')
        ->assertJsonPath('data.description', 'Old description');

    expect($todo->fresh()->title)->toBe('New title')
        ->and($todo->fresh()->description)->toBe('Old description');
});

it('updates all editable fields', function () {
    $user = User::factory()->create();
    $todo = Todo::factory()->pending()->create([
        'user_id' => $user->id,
        'title' => 'Draft release',
        'description' => 'Still pending',
    ]);

    $this->withHeaders(todoUpdateAuthHeader($user))
        ->patchJson('/api/v1/todos/'.$todo->id, [
            'title' => 'Release shipped',
            'description' => 'Completed successfully',
            'status' => TodoStatus::Completed->value,
            'completed_at' => '2026-04-23 14:00:00',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Release shipped')
        ->assertJsonPath('data.status', TodoStatus::Completed->value);
});

it('does not allow changing user_id', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $todo = Todo::factory()->create(['user_id' => $owner->id]);

    $this->withHeaders(todoUpdateAuthHeader($owner))
        ->patchJson('/api/v1/todos/'.$todo->id, [
            'user_id' => $otherUser->id,
            'title' => 'Keep ownership',
        ])
        ->assertOk();

    expect($todo->fresh()->user_id)->toBe($owner->id);
});

it('returns 403 when trying to update another users todo', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $todo = Todo::factory()->create(['user_id' => $owner->id]);

    $this->withHeaders(todoUpdateAuthHeader($intruder))
        ->patchJson('/api/v1/todos/'.$todo->id, [
            'title' => 'Illegal update',
        ])
        ->assertForbidden();
});

it('returns 404 when todo does not exist', function () {
    $user = User::factory()->create();

    $this->withHeaders(todoUpdateAuthHeader($user))
        ->patchJson('/api/v1/todos/999999', [
            'title' => 'Missing todo',
        ])
        ->assertNotFound();
});

it('returns 422 for invalid update payloads', function (array $payload, array $expectedErrors) {
    $user = User::factory()->create();
    $todo = Todo::factory()->create(['user_id' => $user->id]);

    $this->withHeaders(todoUpdateAuthHeader($user))
        ->patchJson('/api/v1/todos/'.$todo->id, $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors($expectedErrors);
})->with([
    'invalid_status' => [
        ['status' => 'archived'],
        ['status'],
    ],
    'invalid_completed_at' => [
        ['completed_at' => 'invalid-date'],
        ['completed_at'],
    ],
    'title_too_long' => [
        ['title' => str_repeat('a', 256)],
        ['title'],
    ],
]);
