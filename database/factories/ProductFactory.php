<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'              => Project::factory(),
            'parent_id'               => null,
            'identifier'              => strtoupper(fake()->unique()->lexify('??')) . fake()->numerify('##'),
            'title'                   => fake()->sentence(),
            'purpose'                 => fake()->optional()->paragraph(),
            'composition'             => fake()->optional()->paragraph(),
            'derivation'              => fake()->optional()->sentence(),
            'format_and_presentation' => fake()->optional()->sentence(),
            'type'                    => fake()->randomElement(ProductType::cases())->value,
            'quality_criteria'        => [fake()->sentence()],
            'quality_tolerance'       => fake()->optional()->sentence(),
            'quality_method'          => fake()->optional()->sentence(),
            'quality_skills_required' => fake()->optional()->sentence(),
            'quality_responsibilities' => [
                'producer' => fake()->name(),
                'reviewer' => fake()->name(),
                'approver' => fake()->name(),
            ],
            'status'                  => ProductStatus::Draft->value,
            'version'                 => 1,
            'created_by'              => Person::factory(),
        ];
    }
}
