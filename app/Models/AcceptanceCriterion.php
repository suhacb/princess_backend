<?php

namespace App\Models;

use App\Enums\AcceptanceCriterionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcceptanceCriterion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'requirement_id',
        'ref',
        'description',
        'measurement_method',
        'acceptance_threshold',
        'status',
        'approved_by',
        'approved_at',
        'supplier_passed',
        'supplier_passed_at',
        'client_passed',
        'client_passed_at',
        'accepted_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status'             => AcceptanceCriterionStatus::class,
        'approved_at'        => 'datetime',
        'supplier_passed'    => 'boolean',
        'supplier_passed_at' => 'datetime',
        'client_passed'      => 'boolean',
        'client_passed_at'   => 'datetime',
        'accepted_at'        => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'updated_by');
    }

    public function testScenarios(): BelongsToMany
    {
        return $this->belongsToMany(
            TestScenario::class,
            'test_scenario_acceptance_criteria'
        );
    }

    public function isDeletable(): bool
    {
        return $this->status === AcceptanceCriterionStatus::Draft
            && $this->testScenarios()->doesntExist();
    }

    public static function nextRef(int $projectId): string
    {
        $count = static::withTrashed()->where('project_id', $projectId)->count();
        return 'AC-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }
}
