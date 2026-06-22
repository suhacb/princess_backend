<?php

namespace App\Models;

use App\Enums\WorkPackageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkPackage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'plan_id',
        'team_manager_id',
        'title',
        'description',
        'techniques_and_processes',
        'development_interfaces',
        'operations_interfaces',
        'configuration_management_requirements',
        'constraints',
        'reporting_requirements',
        'tolerance_time',
        'tolerance_cost',
        'tolerance_scope',
        'tolerance_quality',
        'tolerance_risk',
        'tolerance_benefits',
        'planned_start',
        'planned_end',
        'actual_start',
        'actual_end',
        'status',
        'authorized_by',
        'authorized_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status'         => WorkPackageStatus::class,
        'planned_start'  => 'date',
        'planned_end'    => 'date',
        'actual_start'   => 'date',
        'actual_end'     => 'date',
        'authorized_at'  => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function teamManager(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'team_manager_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'work_package_products');
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'authorized_by');
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
        return $this->status === WorkPackageStatus::Draft;
    }
}
