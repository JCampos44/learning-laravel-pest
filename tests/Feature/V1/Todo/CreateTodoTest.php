<?php

use App\Enums\V1\TodoStatus;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function todoCreateAuthHeader(User $user): array
{
    $plain = $user->createToken('phpunit')->plainTextToken;

    return ['Authorization' => 'Bearer '.$plain];
}

it('returns 401 when no token is provided', function () {
    $this->postJson('/api/v1/todos', [
        'title' => 'Buy groceries',
    ])->assertUnauthorized();
});

it('creates a todo with default pending status when status is omitted', function () {
    $user = User::factory()->create();

    $response = $this->withHeaders(todoCreateAuthHeader($user))
        ->postJson('/api/v1/todos', [
            'title' => 'Buy groceries',
            'description' => 'Milk and eggs',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Buy groceries')
        ->assertJsonPath('data.status', TodoStatus::Pending->value)
        ->assertJsonPath('data.user_id', $user->id);

    $todoId = $response->json('data.id');
    $todo = Todo::find($todoId);

    expect($todo)->not->toBeNull()
        ->and($todo->status)->toBe(TodoStatus::Pending)
        ->and($todo->completed_at)->toBeNull();
});

it('creates a todo with all supported fields', function () {
    $user = User::factory()->create();

    $response = $this->withHeaders(todoCreateAuthHeader($user))
        ->postJson('/api/v1/todos', [
            'title' => 'Ship release',
            'description' => 'Run CI and deploy',
            'status' => TodoStatus::Completed->value,
            'completed_at' => '2026-04-23 12:30:00',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Ship release')
        ->assertJsonPath('data.status', TodoStatus::Completed->value)
        ->assertJsonPath('data.user_id', $user->id);
});

it('always assigns the authenticated user even when user_id is provided in payload', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $response = $this->withHeaders(todoCreateAuthHeader($user))
        ->postJson('/api/v1/todos', [
            'user_id' => $otherUser->id,
            'title' => 'Sensitive task',
        ]);

    $response->assertCreated();

    $todo = Todo::findOrFail($response->json('data.id'));

    expect($todo->user_id)->toBe($user->id);
});

it('returns 422 for invalid payloads', function (array $payload, array $expectedErrors) {
    $user = User::factory()->create();

    $this->withHeaders(todoCreateAuthHeader($user))
        ->postJson('/api/v1/todos', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors($expectedErrors);
})->with([
    'missing_title' => [
        ['description' => 'No title here'],
        ['title'],
    ],
    'title_too_long' => [
        ['title' => str_repeat('a', 256)],
        ['title'],
    ],
    'invalid_status' => [
        ['title' => 'Task', 'status' => 'archived'],
        ['status'],
    ],
    'invalid_completed_at' => [
        ['title' => 'Task', 'completed_at' => 'not-a-date'],
        ['completed_at'],
    ],
]);
