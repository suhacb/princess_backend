<?php

namespace Database\Factories;

use App\Enums\TeamType;
use App\Enums\TestSessionStatus;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'   => Project::factory(),
            'ref'          => 'TSR-' . str_pad(fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'title'        => fake()->sentence(4),
            'session_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'tester_id'    => Person::factory(),
            'team_type'    => fake()->randomElement(TeamType::cases())->value,
            'status'       => TestSessionStatus::Planned->value,
            'created_by'   => Person::factory(),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(['status' => TestSessionStatus::InProgress->value]);
    }

    public function completed(): static
    {
        return $this->state(['status' => TestSessionStatus::Completed->value]);
    }

    public function supplier(): static
    {
        return $this->state(['team_type' => TeamType::Supplier->value]);
    }

    public function client(): static
    {
        return $this->state(['team_type' => TeamType::Client->value]);
    }
}
