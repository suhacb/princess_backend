<?php

namespace Database\Factories;

use App\Enums\AcceptanceCriterionDecision;
use App\Enums\AcceptanceCriterionStatus;
use App\Models\Person;
use App\Models\Project;
use App\Models\Requirement;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcceptanceCriterionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'           => Project::factory(),
            'requirement_id'       => Requirement::factory(),
            'ref'                  => 'AC-' . str_pad(fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'title'                => fake()->sentence(6),
            'description'          => fake()->sentence(10),
            'measurement_method'   => fake()->optional()->sentence(),
            'acceptance_threshold' => fake()->optional()->word(),
            'verifier_id'          => null,
            'verification_method'  => null,
            'status'               => AcceptanceCriterionStatus::Draft->value,
            'version'              => 1,
            'supplier_passed'      => false,
            'client_passed'        => false,
            'supplier_decision'    => AcceptanceCriterionDecision::Pending->value,
            'client_decision'      => AcceptanceCriterionDecision::Pending->value,
            'created_by'           => Person::factory(),
        ];
    }
}
