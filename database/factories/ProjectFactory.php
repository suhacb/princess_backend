<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'          => fake()->words(3, true),
            'reference'     => strtoupper(fake()->bothify('PROJ-####')),
            'description'   => fake()->optional()->paragraph(),
            'status'        => ProjectStatus::PreProject,
            'planned_start' => fake()->optional()->dateTimeBetween('now', '+1 month'),
            'planned_end'   => fake()->optional()->dateTimeBetween('+6 months', '+2 years'),
            'version'       => 1,
            'created_by'    => Person::factory(),
            'updated_by'    => null,
        ];
    }
}
