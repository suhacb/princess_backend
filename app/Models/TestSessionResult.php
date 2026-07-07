<?php

namespace App\Models;

use App\Enums\TestResultStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestSessionResult extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'test_session_id',
        'test_scenario_id',
        'test_case_id',
        'result',
        'step_results',
        'notes',
        'defect_ref',
        'executed_at',
    ];

    protected $casts = [
        'result'       => TestResultStatus::class,
        'step_results' => 'array',
        'executed_at'  => 'datetime',
    ];

    public function testSession(): BelongsTo
    {
        return $this->belongsTo(TestSession::class);
    }

    public function testScenario(): BelongsTo
    {
        return $this->belongsTo(TestScenario::class);
    }

    public function testCase(): BelongsTo
    {
        return $this->belongsTo(TestCase::class);
    }

    /**
     * Worst-of rollup: the first status found in priority order wins.
     * Values may be TestResultStatus instances or their string values.
     */
    public static function worstOf(iterable $results): TestResultStatus
    {
        $priority = [
            TestResultStatus::Fail->value,
            TestResultStatus::Blocked->value,
            TestResultStatus::NotRun->value,
            TestResultStatus::Skipped->value,
            TestResultStatus::Pass->value,
        ];

        $values = collect($results)
            ->map(fn ($result) => $result instanceof TestResultStatus ? $result->value : $result)
            ->all();

        foreach ($priority as $candidate) {
            if (in_array($candidate, $values, true)) {
                return TestResultStatus::from($candidate);
            }
        }

        return TestResultStatus::NotRun;
    }
}
