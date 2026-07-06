<?php

namespace App\Models;

use App\Enums\TestCasePriority;
use App\Enums\TestCaseType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestCase extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'test_scenario_id',
        'project_id',
        'ref',
        'title',
        'steps',
        'expected_result',
        'priority',
        'type',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'steps'    => 'array',
        'priority' => TestCasePriority::class,
        'type'     => TestCaseType::class,
    ];

    protected $attributes = [
        'priority' => 'medium',
    ];

    public function testScenario(): BelongsTo
    {
        return $this->belongsTo(TestScenario::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'updated_by');
    }

    public static function nextRef(int $projectId): string
    {
        $count = static::withTrashed()->where('project_id', $projectId)->count();
        return 'TC-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }
}
