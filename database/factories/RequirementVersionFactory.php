<?php

namespace Database\Factories;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Enums\RequirementType;
use App\Models\Person;
use App\Models\Requirement;
use Illuminate\Database\Eloquent\Factories\Factory;

class RequirementVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'requirement_id' => Requirement::factory(),
            'version_number'  => 1,
            'title'           => fake()->sentence(6),
            'description'     => fake()->optional()->paragraph(),
            'type'            => RequirementType::Classic->value,
            'priority'        => fake()->randomElement(RequirementPriority::cases())->value,
            'status'          => RequirementStatus::Draft->value,
            'role'            => null,
            'action'          => null,
            'benefit'         => null,
            'owner_id'        => null,
            'created_by'      => Person::factory(),
        ];
    }
}
