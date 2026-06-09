<?php

namespace Database\Factories;

use App\Enums\QualityMethod;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class QualityRegisterEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'     => Project::factory(),
            'stage_id'       => null,
            'product_name'   => fake()->words(3, true),
            'quality_method' => fake()->randomElement(QualityMethod::cases())->value,
            'planned_date'   => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
        ];
    }
}
