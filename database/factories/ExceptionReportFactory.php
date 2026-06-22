<?php

namespace Database\Factories;

use App\Enums\ExceptionReportStatus;
use App\Enums\ExceptionTriggerType;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExceptionReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'     => Project::factory(),
            'stage_id'       => null,
            'ref'            => 'EXR-001',
            'title'          => fake()->sentence(4),
            'trigger_type'   => ExceptionTriggerType::Manual->value,
            'description'    => fake()->paragraph(),
            'cause'          => fake()->sentence(),
            'impact'         => fake()->sentence(),
            'options'        => null,
            'recommendation' => fake()->sentence(),
            'status'         => ExceptionReportStatus::Draft->value,
            'board_decision' => null,
            'decided_at'     => null,
            'decided_by'     => null,
            'submitted_at'   => null,
            'submitted_by'   => null,
            'created_by'     => Person::factory(),
            'updated_by'     => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status'       => ExceptionReportStatus::Submitted->value,
            'submitted_at' => now(),
            'submitted_by' => Person::factory(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status'         => ExceptionReportStatus::Closed->value,
            'submitted_at'   => now()->subDay(),
            'submitted_by'   => Person::factory(),
            'board_decision' => 'Approved exception plan.',
            'decided_at'     => now(),
            'decided_by'     => Person::factory(),
        ]);
    }
}
