<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\Project;
use App\Models\TestScenario;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestCaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'test_scenario_id' => TestScenario::factory(),
            'project_id'       => Project::factory(),
            'ref'              => 'TC-' . str_pad(fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'title'            => fake()->sentence(4),
            'steps'            => [
                fake()->sentence(),
                fake()->sentence(),
                fake()->sentence(),
            ],
            'expected_result'  => fake()->sentence(),
            'created_by'       => Person::factory(),
        ];
    }
}
