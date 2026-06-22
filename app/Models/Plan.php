<?php

namespace App\Models;

use App\Enums\PlanStatus;
use App\Enums\PlanType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'type',
        'name',
        'description',
        'stage_id',
        'replaces_plan_id',
        'planned_start',
        'planned_end',
        'actual_start',
        'actual_end',
        'tolerance_time',
        'tolerance_cost',
        'tolerance_scope',
        'tolerance_quality',
        'tolerance_risk',
        'tolerance_benefits',
        'assumptions',
        'external_dependencies',
        'monitoring_and_reporting',
        'status',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type'          => PlanType::class,
        'status'        => PlanStatus::class,
        'planned_start' => 'date',
        'planned_end'   => 'date',
        'actual_start'  => 'date',
        'actual_end'    => 'date',
        'approved_at'   => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function replaces(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'replaces_plan_id');
    }

    public function workPackages(): HasMany
    {
        return $this->hasMany(WorkPackage::class);
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

    public function isDeletable(): bool
    {
        return $this->status === PlanStatus::Draft;
    }
}
