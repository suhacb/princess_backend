<?php

namespace Database\Factories;

use App\Enums\StageStatus;
use App\Enums\StageType;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class StageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'  => Project::factory(),
            'name'        => fake()->words(2, true),
            'type'        => StageType::Delivery,
            'sequence'    => fake()->numberBetween(1, 10),
            'description' => fake()->optional()->paragraph(),
            'status'      => StageStatus::Planned,
            'version'     => 1,
            'created_by'  => Person::factory(),
            'updated_by'  => null,
        ];
    }
}
