<?php

namespace App\Http\Controllers\V1;

use App\Enums\V1\TodoStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Todo\CreateTodoRequest;
use App\Http\Resources\V1\TodoResource;
use App\Models\Todo;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TodoController extends Controller
{
    /**
     * List the authenticated user's todos.
     *
     * Returns all todos that belong to the currently authenticated user. The
     * result supports sparse fieldsets, filtering by title and status, filtering
     * soft-deleted records, and sorting.
     */
    #[Group('Todos')]
    #[Authenticated]
    #[Header('Authorization', 'Bearer {token}')]
    #[QueryParam(
        'fields[todos]',
        description: 'Comma-separated list of fields to include in the response.',
        required: false,
        example: 'id,title,status,created_at'
    )]
    #[QueryParam(
        'filter[title]',
        description: 'Filter todos by a partial title match.',
        required: false,
        example: 'groceries',
        nullable: true
    )]
    #[QueryParam(
        'filter[status]',
        description: 'Filter todos by exact status.',
        required: false,
        example: TodoStatus::Pending->value,
        enum: TodoStatus::class,
        nullable: true
    )]
    #[QueryParam(
        'filter[trashed]',
        description: 'Include soft-deleted records.',
        required: false,
        example: 'with',
        enum: ['with', 'only']
    )]
    #[QueryParam(
        'sort',
        description: 'Sort results by id, title, or created_at. Prefix with - for descending order.',
        required: false,
        example: '-created_at'
    )]
    #[Response([
        'data' => [
            [
                'id' => 1,
                'user_id' => 1,
                'title' => 'Buy groceries',
                'description' => 'Milk, bread, eggs, and coffee',
                'status' => 'pending',
                'completed_at' => null,
                'created_at' => '2026-04-10T15:30:00.000000Z',
                'updated_at' => '2026-04-10T15:30:00.000000Z',
            ],
        ],
    ], status: 200)]
    #[Response(['message' => 'Unauthenticated.'], status: 401)]
    public function index()
    {
        $todos = QueryBuilder::for(Todo::class)
            ->allowedFields('id', 'user_id', 'title', 'description', 'status', 'completed_at', 'created_at', 'updated_at')
            ->allowedFilters(
                AllowedFilter::partial('title')->nullable(),
                AllowedFilter::exact('status')->nullable(),
                AllowedFilter::trashed()
            )
            ->allowedSorts('id', 'title', 'created_at')
            ->where('user_id', auth()->user()->id)
            ->get();

        return TodoResource::collection($todos)
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Create a new todo for the authenticated user.
     *
     * Stores a new todo owned by the currently authenticated user. If `status`
     * is not provided, the todo is created with the default `pending` status.
     */
    #[Group('Todos')]
    #[Authenticated]
    #[Header('Authorization', 'Bearer {token}')]
    #[BodyParam('title', 'string', 'The todo title. Maximum 255 characters.', true, 'Buy groceries')]
    #[BodyParam('description', 'string', 'Optional details about the todo.', false, 'Milk, bread, eggs, and coffee', nullable: true)]
    #[BodyParam('status', 'string', 'The todo status. Defaults to `pending`.', false, TodoStatus::Pending->value, enum: TodoStatus::class, nullable: true)]
    #[BodyParam('completed_at', 'string', 'Completion date in a valid datetime format.', false, '2026-04-10 15:30:00', nullable: true)]
    #[Response([
        'data' => [
            'id' => 1,
            'user_id' => 1,
            'title' => 'Buy groceries',
            'description' => 'Milk, bread, eggs, and coffee',
            'status' => 'pending',
            'completed_at' => null,
            'created_at' => '2026-04-10T15:30:00.000000Z',
            'updated_at' => '2026-04-10T15:30:00.000000Z',
        ],
    ], status: 201)]
    #[Response(['message' => 'Unauthenticated.'], status: 401)]
    #[Response([
        'message' => 'The given data was invalid.',
        'errors' => [
            'title' => [
                'The title field is required.',
            ],
        ],
    ], status: 422)]
    public function store(CreateTodoRequest $request)
    {
        $validated = $request->validated();

        $todo = Todo::create([
            'user_id' => auth()->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? TodoStatus::Pending,
            'completed_at' => $validated['completed_at'] ?? null,
        ]);

        return (new TodoResource($todo))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Todo $todo)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Todo $todo)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Todo $todo)
    {
        //
    }
}
