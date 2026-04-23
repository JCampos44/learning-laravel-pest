<?php

namespace Database\Factories;

use App\Enums\V1\TodoStatus;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Todo>
 */
class TodoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isCompleted = fake()->boolean();

        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'status' => $isCompleted ? TodoStatus::Completed->value : TodoStatus::Pending->value,
            'completed_at' => $isCompleted ? now()->subDay() : null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => TodoStatus::Pending->value,
            'completed_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => TodoStatus::Completed->value,
            'completed_at' => now()->subMinute(),
        ]);
    }
}
