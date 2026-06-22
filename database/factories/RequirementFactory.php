<?php

namespace Database\Factories;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Enums\RequirementType;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class RequirementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'type'       => RequirementType::Classic->value,
            'parent_id'  => null,
            'ref'        => 'REQ-' . str_pad(fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'title'      => fake()->sentence(6),
            'description' => fake()->optional()->paragraph(),
            'role'       => null,
            'action'     => null,
            'benefit'    => null,
            'priority'   => fake()->randomElement(RequirementPriority::cases())->value,
            'status'     => RequirementStatus::Draft->value,
            'source'     => null,
            'owner_id'   => null,
            'version'    => 1,
            'created_by' => Person::factory(),
        ];
    }

    public function epic(): static
    {
        return $this->state(['type' => RequirementType::Epic->value]);
    }

    public function userStory(): static
    {
        return $this->state([
            'type'    => RequirementType::UserStory->value,
            'ref'     => 'US-' . str_pad(fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'role'    => fake()->jobTitle(),
            'action'  => fake()->sentence(5),
            'benefit' => fake()->sentence(6),
        ]);
    }
}
