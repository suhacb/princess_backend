<?php

namespace App\Models;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Enums\RequirementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Requirement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'type',
        'parent_id',
        'ref',
        'title',
        'description',
        'role',
        'action',
        'benefit',
        'priority',
        'status',
        'source',
        'owner_id',
        'version',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type'        => RequirementType::class,
        'priority'    => RequirementPriority::class,
        'status'      => RequirementStatus::class,
        'approved_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Requirement::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Requirement::class, 'parent_id');
    }

    public function acceptanceCriteria(): HasMany
    {
        return $this->hasMany(AcceptanceCriterion::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'owner_id');
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

    public function qaDocuments(): BelongsToMany
    {
        return $this->belongsToMany(QaDocument::class, 'qa_document_requirements');
    }

    public function isDeletable(): bool
    {
        if ($this->status !== RequirementStatus::Draft) {
            return false;
        }
        if ($this->type === RequirementType::Epic && $this->children()->exists()) {
            return false;
        }
        return true;
    }

    public static function nextRef(int $projectId, RequirementType $type): string
    {
        if ($type === RequirementType::UserStory) {
            $count = static::withTrashed()
                ->where('project_id', $projectId)
                ->where('type', RequirementType::UserStory->value)
                ->count();
            return 'US-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        }

        $count = static::withTrashed()
            ->where('project_id', $projectId)
            ->whereIn('type', [RequirementType::Classic->value, RequirementType::Epic->value])
            ->count();
        return 'REQ-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }
}
