<?php

namespace Database\Factories;

use App\Enums\LessonSource;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class LessonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'     => Project::factory(),
            'stage_id'       => null,
            'category'       => fake()->optional()->word(),
            'description'    => fake()->paragraph(),
            'recommendation' => fake()->optional()->sentence(),
            'raised_by'      => Person::factory(),
            'raised_at'      => now(),
            'source'         => fake()->randomElement(LessonSource::cases())->value,
        ];
    }
}
