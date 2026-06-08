<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'status'  => 'pre_project',
        'version' => 1,
    ];

    protected $fillable = [
        'name',
        'reference',
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
        'status'        => ProjectStatus::class,
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'updated_by');
    }
}
