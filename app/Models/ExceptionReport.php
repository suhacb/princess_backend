<?php

namespace App\Models;

use App\Enums\ExceptionReportStatus;
use App\Enums\ExceptionTriggerType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExceptionReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'stage_id',
        'ref',
        'title',
        'trigger_type',
        'description',
        'cause',
        'impact',
        'options',
        'recommendation',
        'status',
        'board_decision',
        'decided_at',
        'decided_by',
        'submitted_at',
        'submitted_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status'       => ExceptionReportStatus::class,
        'trigger_type' => ExceptionTriggerType::class,
        'options'      => 'array',
        'submitted_at' => 'datetime',
        'decided_at'   => 'datetime',
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

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'decided_by');
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
        return $this->status === ExceptionReportStatus::Draft;
    }

    public static function nextRef(int $projectId): string
    {
        $count = static::withTrashed()->where('project_id', $projectId)->count();
        return 'EXR-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }
}
