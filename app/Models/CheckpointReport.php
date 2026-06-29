<?php

namespace App\Models;

use App\Enums\CheckpointReportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CheckpointReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'work_package_id',
        'ref',
        'title',
        'period_from',
        'period_to',
        'status',
        'achievements',
        'planned_next_period',
        'issues_this_period',
        'issues_forecast',
        'quality_notes',
        'submitted_at',
        'submitted_by',
        'acknowledged_at',
        'acknowledged_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status'          => CheckpointReportStatus::class,
        'period_from'     => 'date',
        'period_to'       => 'date',
        'submitted_at'    => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'submitted_by');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'acknowledged_by');
    }

    public function document(): MorphOne
    {
        return $this->morphOne(QaDocument::class, 'documentable');
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
        return $this->status === CheckpointReportStatus::Draft;
    }

    public static function nextRef(int $projectId): string
    {
        $count = static::withTrashed()->where('project_id', $projectId)->count();
        return 'CPR-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }
}
