<?php

namespace App\Models;

use App\Enums\TeamType;
use App\Enums\TestSessionPlanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestSessionPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'ref',
        'title',
        'description',
        'planned_date',
        'team_type',
        'assignee_id',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'team_type'    => TeamType::class,
        'status'       => TestSessionPlanStatus::class,
        'planned_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scenarios(): BelongsToMany
    {
        return $this->belongsToMany(
            TestScenario::class,
            'test_session_plan_scenarios'
        )->withPivot('order')->orderByPivot('order');
    }

    public function testSessions(): HasMany
    {
        return $this->hasMany(TestSession::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'assignee_id');
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
        return $this->status === TestSessionPlanStatus::Draft;
    }

    public static function nextRef(int $projectId): string
    {
        $count = static::withTrashed()->where('project_id', $projectId)->count();
        return 'TSP-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }
}
