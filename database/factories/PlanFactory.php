<?php

namespace Database\Factories;

use App\Enums\PlanStatus;
use App\Enums\PlanType;
use App\Models\Person;
use App\Models\Project;
use App\Models\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'    => Project::factory(),
            'type'          => PlanType::Stage->value,
            'name'          => fake()->sentence(4),
            'description'   => fake()->optional()->paragraph(),
            'stage_id'      => Stage::factory(),
            'planned_start' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'planned_end'   => fake()->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'status'        => PlanStatus::Draft->value,
            'created_by'    => Person::factory(),
        ];
    }

    public function team(): static
    {
        return $this->state(['type' => PlanType::Team->value, 'stage_id' => null]);
    }
}
