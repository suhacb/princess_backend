<?php

namespace Database\Factories;

use App\Enums\TestScenarioStatus;
use App\Enums\TestScenarioType;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestScenarioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'   => Project::factory(),
            'ref'          => 'TS-' . str_pad(fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'title'        => fake()->sentence(5),
            'description'  => fake()->optional()->paragraph(),
            'preconditions' => fake()->optional()->sentence(),
            'type'         => fake()->randomElement(TestScenarioType::cases())->value,
            'status'       => TestScenarioStatus::Draft->value,
            'is_testable'  => false,
            'created_by'   => Person::factory(),
        ];
    }

    public function ready(): static
    {
        return $this->state(['status' => TestScenarioStatus::Ready->value]);
    }

    public function obsolete(): static
    {
        return $this->state(['status' => TestScenarioStatus::Obsolete->value]);
    }

    public function testable(): static
    {
        return $this->state(['is_testable' => true]);
    }

    public function feature(): static
    {
        return $this->state(['type' => TestScenarioType::Feature->value]);
    }

    public function e2e(): static
    {
        return $this->state(['type' => TestScenarioType::E2E->value]);
    }
}
