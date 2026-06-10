<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectProductDescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'title',
        'purpose',
        'composition',
        'derivation',
        'format_and_presentation',
        'quality_criteria',
        'quality_tolerance',
        'quality_method',
        'quality_skills_required',
        'quality_responsibilities',
        'customer_quality_expectations',
        'acceptance_criteria',
        'acceptance_methods',
        'acceptance_responsibilities',
        'version',
        'baselined_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'quality_criteria'        => 'array',
        'quality_responsibilities' => 'array',
        'acceptance_criteria'     => 'array',
        'baselined_at'            => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'updated_by');
    }

    public function isBaselined(): bool
    {
        return $this->baselined_at !== null;
    }
}
