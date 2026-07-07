<?php

namespace Database\Factories;

use App\Enums\TestResultStatus;
use App\Models\TestScenario;
use App\Models\TestSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestSessionResultFactory extends Factory
{
    public function definition(): array
    {
        return [
            'test_session_id'  => TestSession::factory(),
            'test_scenario_id' => TestScenario::factory(),
            'test_case_id'     => null,
            'result'           => TestResultStatus::NotRun->value,
        ];
    }

    public function forTestCase(int $testCaseId): static
    {
        return $this->state(['test_case_id' => $testCaseId]);
    }

    public function pass(): static
    {
        return $this->state(['result' => TestResultStatus::Pass->value, 'executed_at' => now()]);
    }

    public function fail(): static
    {
        return $this->state(['result' => TestResultStatus::Fail->value, 'executed_at' => now()]);
    }
}
