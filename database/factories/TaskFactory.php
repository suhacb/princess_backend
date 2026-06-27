<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'  => Project::factory(),
            'title'       => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'due_date'    => fake()->optional()->dateTimeBetween('now', '+3 months')?->format('Y-m-d'),
            'status'      => TaskStatus::Todo->value,
            'priority'    => fake()->randomElement(TaskPriority::cases())->value,
            'created_by'  => Person::factory(),
        ];
    }
}
