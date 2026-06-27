<?php

namespace App\Models;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Traits\IsAuditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    use HasFactory, IsAuditable;

    protected array $auditableFields = [
        'title', 'description', 'issue_type', 'priority', 'status', 'assigned_to', 'resolution', 'resolved_at', 'escalated_at',
    ];

    protected $fillable = [
        'project_id',
        'stage_id',
        'issue_type',
        'title',
        'description',
        'raised_by',
        'raised_at',
        'priority',
        'status',
        'assigned_to',
        'resolution',
        'resolved_at',
        'escalated_at',
        'escalation_reason',
    ];

    protected $casts = [
        'issue_type'   => IssueType::class,
        'priority'     => IssuePriority::class,
        'status'       => IssueStatus::class,
        'raised_at'    => 'datetime',
        'resolved_at'  => 'datetime',
        'escalated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'raised_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'assigned_to');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(Change::class);
    }
}
