<?php

namespace App\Models;

use App\Enums\TestResultStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestSessionResult extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'test_session_id',
        'test_scenario_id',
        'result',
        'notes',
        'defect_ref',
        'executed_at',
    ];

    protected $casts = [
        'result'      => TestResultStatus::class,
        'executed_at' => 'datetime',
    ];

    public function testSession(): BelongsTo
    {
        return $this->belongsTo(TestSession::class);
    }

    public function testScenario(): BelongsTo
    {
        return $this->belongsTo(TestScenario::class);
    }
}
