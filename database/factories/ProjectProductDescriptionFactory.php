<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectProductDescriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'                    => Project::factory(),
            'title'                         => fake()->sentence(),
            'purpose'                       => fake()->paragraph(),
            'composition'                   => fake()->optional()->paragraph(),
            'derivation'                    => fake()->optional()->sentence(),
            'format_and_presentation'       => fake()->optional()->sentence(),
            'quality_criteria'              => [fake()->sentence(), fake()->sentence()],
            'quality_tolerance'             => fake()->optional()->sentence(),
            'quality_method'                => fake()->optional()->sentence(),
            'quality_skills_required'       => fake()->optional()->sentence(),
            'quality_responsibilities'      => [
                'producer' => fake()->name(),
                'reviewer' => fake()->name(),
                'approver' => fake()->name(),
            ],
            'customer_quality_expectations' => fake()->optional()->paragraph(),
            'acceptance_criteria'           => [fake()->sentence(), fake()->sentence()],
            'acceptance_methods'            => fake()->optional()->sentence(),
            'acceptance_responsibilities'   => fake()->optional()->sentence(),
            'version'                       => 1,
            'created_by'                    => Person::factory(),
        ];
    }
}
