<?php

namespace Database\Factories;

use App\Enums\TeamType;
use App\Enums\TestSessionPlanStatus;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestSessionPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'  => Project::factory(),
            'ref'         => 'TSP-' . str_pad(fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'title'       => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'planned_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'team_type'   => fake()->randomElement(TeamType::cases())->value,
            'status'      => TestSessionPlanStatus::Draft->value,
            'created_by'  => Person::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => TestSessionPlanStatus::Active->value]);
    }

    public function completed(): static
    {
        return $this->state(['status' => TestSessionPlanStatus::Completed->value]);
    }
}
