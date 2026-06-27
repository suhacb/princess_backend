<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Task extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'project_id',
        'stage_id',
        'work_package_id',
        'title',
        'description',
        'assigned_to',
        'due_date',
        'status',
        'priority',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status'   => TaskStatus::class,
        'priority' => TaskPriority::class,
        'due_date' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'description', 'assigned_to', 'due_date', 'status', 'priority', 'stage_id', 'work_package_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'assigned_to');
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
