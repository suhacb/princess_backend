<?php

namespace Database\Factories;

use App\Enums\WorkPackageStatus;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkPackageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'      => Project::factory(),
            'plan_id'         => null,
            'team_manager_id' => Person::factory(),
            'title'           => fake()->sentence(4),
            'description'     => fake()->optional()->paragraph(),
            'planned_start'   => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'planned_end'     => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'status'          => WorkPackageStatus::Draft->value,
            'created_by'      => Person::factory(),
        ];
    }
}
