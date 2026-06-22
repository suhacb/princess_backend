<?php

namespace App\Models;

use App\Enums\HighlightReportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HighlightReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'stage_id',
        'ref',
        'title',
        'period_from',
        'period_to',
        'status',
        'budget_status',
        'schedule_status',
        'this_period_work',
        'next_period_work',
        'issues_summary',
        'risks_summary',
        'quality_summary',
        'business_case_review',
        'forecast_finish',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status'         => HighlightReportStatus::class,
        'period_from'    => 'date',
        'period_to'      => 'date',
        'forecast_finish' => 'date',
        'submitted_at'   => 'datetime',
        'approved_at'    => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'submitted_by');
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
        return $this->status === HighlightReportStatus::Draft;
    }

    public static function nextRef(int $projectId): string
    {
        $count = static::withTrashed()->where('project_id', $projectId)->count();
        return 'HLR-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }
}
