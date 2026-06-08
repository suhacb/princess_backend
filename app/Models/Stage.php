<?php

namespace App\Models;

use App\Enums\StageStatus;
use App\Enums\StageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'name',
        'type',
        'sequence',
        'description',
        'status',
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
        'type'          => StageType::class,
        'status'        => StageStatus::class,
        'planned_start' => 'date',
        'planned_end'   => 'date',
        'actual_start'  => 'date',
        'actual_end'    => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function boundaries(): HasMany
    {
        return $this->hasMany(StageBoundary::class);
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
