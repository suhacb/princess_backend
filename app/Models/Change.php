<?php

namespace App\Models;

use App\Enums\ChangeRequestType;
use App\Enums\ChangeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Change extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'description', 'request_type', 'priority', 'status', 'decision_rationale', 'implementation_due', 'implemented_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function beforeActivityLogged(Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->put('project_id', $this->project_id);
    }

    protected $table = 'changes';

    protected $fillable = [
        'project_id',
        'issue_id',
        'request_type',
        'title',
        'description',
        'raised_by',
        'raised_at',
        'impact_assessment',
        'priority',
        'status',
        'decision_by',
        'decision_at',
        'decision_rationale',
        'implementation_due',
        'implemented_at',
    ];

    protected $casts = [
        'request_type'      => ChangeRequestType::class,
        'status'            => ChangeStatus::class,
        'raised_at'         => 'datetime',
        'decision_at'       => 'datetime',
        'implementation_due' => 'date',
        'implemented_at'    => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'raised_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'decision_by');
    }
}
