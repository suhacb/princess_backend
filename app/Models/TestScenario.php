<?php

namespace App\Models;

use App\Enums\TestScenarioStatus;
use App\Enums\TestScenarioType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class TestScenario extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'ref',
        'title',
        'description',
        'preconditions',
        'type',
        'status',
        'is_testable',
        'testable_notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type'        => TestScenarioType::class,
        'status'      => TestScenarioStatus::class,
        'is_testable' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function testCases(): HasMany
    {
        return $this->hasMany(TestCase::class)->orderBy('id');
    }

    public function acceptanceCriteria(): BelongsToMany
    {
        return $this->belongsToMany(
            AcceptanceCriterion::class,
            'test_scenario_acceptance_criteria'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'updated_by');
    }

    public function isDeletable(): bool
    {
        if ($this->status !== TestScenarioStatus::Draft) {
            return false;
        }

        // Check for test session results once B3 is deployed
        if (Schema::hasTable('test_session_results')) {
            if ($this->testSessionResults()->exists()) {
                return false;
            }
        }

        return true;
    }

    public static function nextRef(int $projectId): string
    {
        $count = static::withTrashed()->where('project_id', $projectId)->count();
        return 'TS-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }
}
