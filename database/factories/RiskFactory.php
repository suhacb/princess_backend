<?php

namespace Database\Factories;

use App\Enums\RiskProximity;
use App\Enums\RiskResponseType;
use App\Enums\RiskStatus;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class RiskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'    => Project::factory(),
            'stage_id'      => null,
            'title'         => fake()->sentence(),
            'description'   => fake()->optional()->paragraph(),
            'category'      => fake()->optional()->word(),
            'probability'   => fake()->numberBetween(1, 5),
            'impact'        => fake()->numberBetween(1, 5),
            'proximity'     => fake()->randomElement(RiskProximity::cases())->value,
            'risk_owner'    => Person::factory(),
            'response_type' => fake()->randomElement(RiskResponseType::cases())->value,
            'status'        => RiskStatus::Open->value,
        ];
    }
}
