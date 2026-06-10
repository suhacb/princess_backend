<?php

namespace Database\Factories;

use App\Enums\ChangeRequestType;
use App\Enums\ChangeStatus;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChangeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'   => Project::factory(),
            'issue_id'     => null,
            'request_type' => fake()->randomElement(ChangeRequestType::cases())->value,
            'title'        => fake()->sentence(),
            'description'  => fake()->optional()->paragraph(),
            'raised_by'    => Person::factory(),
            'raised_at'    => now(),
            'status'       => ChangeStatus::Proposed->value,
        ];
    }
}
