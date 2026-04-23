<?php

use App\Enums\V1\TodoStatus;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;

uses(LazilyRefreshDatabase::class);

function todoAuthHeader(User $user): array
{
    $plain = $user->createToken('phpunit')->plainTextToken;

    return ['Authorization' => 'Bearer '.$plain];
}

it('returns 401 when no token is provided', function () {
    $this->getJson('/api/v1/todos')->assertUnauthorized();
});

it('returns only todos that belong to the authenticated user', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownerTodo = Todo::factory()->create(['user_id' => $owner->id, 'title' => 'Owner todo']);
    Todo::factory()->create(['user_id' => $otherUser->id, 'title' => 'Other todo']);

    $response = $this->withHeaders(todoAuthHeader($owner))->getJson('/api/v1/todos');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownerTodo->id)
        ->assertJsonPath('data.0.title', 'Owner todo');
});

it('filters todos by partial title', function () {
    $user = User::factory()->create();

    Todo::factory()->create(['user_id' => $user->id, 'title' => 'Buy groceries']);
    Todo::factory()->create(['user_id' => $user->id, 'title' => 'Read a book']);

    $response = $this->withHeaders(todoAuthHeader($user))
        ->getJson('/api/v1/todos?filter[title]=grocer');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Buy groceries');
});

it('filters todos by exact status', function () {
    $user = User::factory()->create();

    Todo::factory()->pending()->create(['user_id' => $user->id]);
    Todo::factory()->completed()->create(['user_id' => $user->id]);

    $response = $this->withHeaders(todoAuthHeader($user))
        ->getJson('/api/v1/todos?filter[status]='.TodoStatus::Completed->value);

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', TodoStatus::Completed->value);
});

it('includes soft-deleted todos when using trashed with', function () {
    $user = User::factory()->create();

    Todo::factory()->create(['user_id' => $user->id, 'title' => 'Visible']);
    $trashed = Todo::factory()->create(['user_id' => $user->id, 'title' => 'Deleted']);
    $trashed->delete();

    $response = $this->withHeaders(todoAuthHeader($user))
        ->getJson('/api/v1/todos?filter[trashed]=with');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('returns only soft-deleted todos when using trashed only', function () {
    $user = User::factory()->create();

    Todo::factory()->create(['user_id' => $user->id, 'title' => 'Visible']);
    $trashed = Todo::factory()->create(['user_id' => $user->id, 'title' => 'Deleted']);
    $trashed->delete();

    $response = $this->withHeaders(todoAuthHeader($user))
        ->getJson('/api/v1/todos?filter[trashed]=only');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $trashed->id);
});

it('sorts by created_at ascending by default', function () {
    Carbon::setTestNow('2026-04-23 10:00:00');

    $user = User::factory()->create();
    $oldest = Todo::factory()->create(['user_id' => $user->id, 'created_at' => '2026-04-20 10:00:00']);
    $newest = Todo::factory()->create(['user_id' => $user->id, 'created_at' => '2026-04-22 10:00:00']);

    $response = $this->withHeaders(todoAuthHeader($user))->getJson('/api/v1/todos');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $oldest->id)
        ->assertJsonPath('data.1.id', $newest->id);

    Carbon::setTestNow();
});

it('sorts by created_at descending when using -created_at', function () {
    $user = User::factory()->create();

    $oldest = Todo::factory()->create(['user_id' => $user->id, 'created_at' => '2026-04-20 10:00:00']);
    $newest = Todo::factory()->create(['user_id' => $user->id, 'created_at' => '2026-04-22 10:00:00']);

    $response = $this->withHeaders(todoAuthHeader($user))
        ->getJson('/api/v1/todos?sort=-created_at');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $newest->id)
        ->assertJsonPath('data.1.id', $oldest->id);
});

it('supports sparse fieldsets', function () {
    $user = User::factory()->create();

    Todo::factory()->pending()->create(['user_id' => $user->id]);

    $response = $this->withHeaders(todoAuthHeader($user))
        ->getJson('/api/v1/todos?fields[todos]=id,title,status');

    $response->assertOk()
        ->assertJsonPath('data.0.status', TodoStatus::Pending->value)
        ->assertJsonMissingPath('data.0.user_id')
        ->assertJsonMissingPath('data.0.description')
        ->assertJsonMissingPath('data.0.completed_at');
});
