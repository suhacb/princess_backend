<?php

namespace Database\Factories;

use App\Enums\AcceptanceCriterionDecision;
use App\Enums\AcceptanceCriterionStatus;
use App\Models\AcceptanceCriterion;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcceptanceCriterionVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'acceptance_criterion_id' => AcceptanceCriterion::factory(),
            'version_number'          => 1,
            'title'                   => fake()->sentence(6),
            'description'             => fake()->paragraph(),
            'verifier_id'             => null,
            'verification_method'     => null,
            'status'                  => AcceptanceCriterionStatus::Draft->value,
            'supplier_passed'         => false,
            'client_passed'           => false,
            'supplier_decision'       => AcceptanceCriterionDecision::Pending->value,
            'client_decision'         => AcceptanceCriterionDecision::Pending->value,
            'created_by'              => Person::factory(),
        ];
    }
}
