<?php

namespace App\Models;

use App\Enums\DocumentProvider;
use App\Enums\ProjectStatus;
use App\Traits\IsAuditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes, IsAuditable;

    protected array $auditableFields = [
        'name', 'description', 'status', 'planned_start', 'planned_end', 'actual_start', 'actual_end',
    ];

    protected function resolveProjectId(): ?int
    {
        return $this->id;
    }

    protected $attributes = [
        'status'  => 'pre_project',
        'version' => 1,
    ];

    protected $fillable = [
        'name',
        'reference',
        'document_provider',
        'description',
        'status',
        'current_stage_id',
        'planned_start',
        'planned_end',
        'actual_start',
        'actual_end',
        'tolerance_time',
        'tolerance_cost',
        'tolerance_scope',
        'tolerance_risk',
        'tolerance_quality',
        'tolerance_benefit',
        'version',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status'            => ProjectStatus::class,
        'document_provider' => DocumentProvider::class,
        'planned_start' => 'date',
        'planned_end'   => 'date',
        'actual_start'  => 'date',
        'actual_end'    => 'date',
    ];

    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class)->orderBy('sequence');
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'current_stage_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function dailyLogEntries(): HasMany
    {
        return $this->hasMany(DailyLogEntry::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function risks(): HasMany
    {
        return $this->hasMany(Risk::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(Change::class);
    }

    public function qualityRegisterEntries(): HasMany
    {
        return $this->hasMany(QualityRegisterEntry::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    public function productDescription(): HasOne
    {
        return $this->hasOne(ProjectProductDescription::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function workPackages(): HasMany
    {
        return $this->hasMany(WorkPackage::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class);
    }

    public function acceptanceCriteria(): HasMany
    {
        return $this->hasMany(AcceptanceCriterion::class);
    }

    public function qaDocuments(): HasMany
    {
        return $this->hasMany(QaDocument::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class);
    }

    public function testScenarios(): HasMany
    {
        return $this->hasMany(TestScenario::class);
    }

    public function testCases(): HasMany
    {
        return $this->hasMany(TestCase::class);
    }

    public function testSessionPlans(): HasMany
    {
        return $this->hasMany(TestSessionPlan::class);
    }

    public function testSessions(): HasMany
    {
        return $this->hasMany(TestSession::class);
    }

    public function checkpointReports(): HasMany
    {
        return $this->hasMany(CheckpointReport::class);
    }

    public function highlightReports(): HasMany
    {
        return $this->hasMany(HighlightReport::class);
    }

    public function exceptionReports(): HasMany
    {
        return $this->hasMany(ExceptionReport::class);
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
}
