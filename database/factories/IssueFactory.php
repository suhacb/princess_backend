<?php

namespace Database\Factories;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class IssueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'  => Project::factory(),
            'stage_id'    => null,
            'issue_type'  => fake()->randomElement(IssueType::cases())->value,
            'title'       => fake()->sentence(),
            'description' => fake()->optional()->paragraph(),
            'raised_by'   => Person::factory(),
            'raised_at'   => now(),
            'priority'    => fake()->randomElement(IssuePriority::cases())->value,
            'status'      => IssueStatus::Open->value,
            'assigned_to' => null,
        ];
    }
}
