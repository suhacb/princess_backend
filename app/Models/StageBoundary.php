<?php

namespace App\Models;

use App\Enums\BoundaryStatus;
use App\Enums\BoundaryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageBoundary extends Model
{
    use HasFactory;

    protected $fillable = [
        'stage_id',
        'type',
        'status',
        'title',
        'notes',
        'next_stage_id',
        'exception_summary',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type'         => BoundaryType::class,
        'status'       => BoundaryStatus::class,
        'submitted_at' => 'datetime',
        'approved_at'  => 'datetime',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function nextStage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'next_stage_id');
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
}
