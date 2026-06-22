<?php

namespace Database\Factories;

use App\Enums\CheckpointReportStatus;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class CheckpointReportFactory extends Factory
{
    public function definition(): array
    {
        $from = fake()->dateTimeBetween('-2 months', '-1 month');
        $to   = fake()->dateTimeBetween('-1 month', 'now');

        return [
            'project_id'           => Project::factory(),
            'work_package_id'      => null,
            'ref'                  => 'CPR-001',
            'title'                => fake()->sentence(4),
            'period_from'          => $from->format('Y-m-d'),
            'period_to'            => $to->format('Y-m-d'),
            'status'               => CheckpointReportStatus::Draft->value,
            'achievements'         => fake()->paragraph(),
            'planned_next_period'  => fake()->paragraph(),
            'issues_this_period'   => null,
            'issues_forecast'      => null,
            'quality_notes'        => null,
            'submitted_at'         => null,
            'submitted_by'         => null,
            'acknowledged_at'      => null,
            'acknowledged_by'      => null,
            'created_by'           => Person::factory(),
            'updated_by'           => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status'       => CheckpointReportStatus::Submitted->value,
            'submitted_at' => now(),
            'submitted_by' => Person::factory(),
        ]);
    }

    public function acknowledged(): static
    {
        return $this->state(fn () => [
            'status'          => CheckpointReportStatus::Acknowledged->value,
            'submitted_at'    => now()->subDay(),
            'submitted_by'    => Person::factory(),
            'acknowledged_at' => now(),
            'acknowledged_by' => Person::factory(),
        ]);
    }
}
