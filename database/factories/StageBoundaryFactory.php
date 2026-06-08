<?php

namespace Database\Factories;

use App\Enums\BoundaryStatus;
use App\Enums\BoundaryType;
use App\Models\Person;
use App\Models\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;

class StageBoundaryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'stage_id'   => Stage::factory(),
            'type'       => BoundaryType::EndStageReport,
            'status'     => BoundaryStatus::Draft,
            'title'      => fake()->optional()->sentence(),
            'notes'      => fake()->optional()->paragraph(),
            'created_by' => Person::factory(),
            'updated_by' => null,
        ];
    }
}
