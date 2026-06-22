<?php

namespace Database\Factories;

use App\Enums\HighlightReportStatus;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class HighlightReportFactory extends Factory
{
    public function definition(): array
    {
        $from = fake()->dateTimeBetween('-2 months', '-1 month');
        $to   = fake()->dateTimeBetween('-1 month', 'now');

        return [
            'project_id'           => Project::factory(),
            'stage_id'             => null,
            'ref'                  => 'HLR-001',
            'title'                => fake()->sentence(4),
            'period_from'          => $from->format('Y-m-d'),
            'period_to'            => $to->format('Y-m-d'),
            'status'               => HighlightReportStatus::Draft->value,
            'budget_status'        => null,
            'schedule_status'      => null,
            'this_period_work'     => fake()->paragraph(),
            'next_period_work'     => fake()->paragraph(),
            'issues_summary'       => null,
            'risks_summary'        => null,
            'quality_summary'      => null,
            'business_case_review' => null,
            'forecast_finish'      => null,
            'submitted_at'         => null,
            'submitted_by'         => null,
            'approved_at'          => null,
            'approved_by'          => null,
            'created_by'           => Person::factory(),
            'updated_by'           => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status'       => HighlightReportStatus::Submitted->value,
            'submitted_at' => now(),
            'submitted_by' => Person::factory(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status'       => HighlightReportStatus::Approved->value,
            'submitted_at' => now()->subDay(),
            'submitted_by' => Person::factory(),
            'approved_at'  => now(),
            'approved_by'  => Person::factory(),
        ]);
    }
}
