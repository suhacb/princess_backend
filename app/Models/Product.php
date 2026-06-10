<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'parent_id',
        'identifier',
        'title',
        'purpose',
        'composition',
        'derivation',
        'format_and_presentation',
        'type',
        'quality_criteria',
        'quality_tolerance',
        'quality_method',
        'quality_skills_required',
        'quality_responsibilities',
        'status',
        'version',
        'baselined_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type'                    => ProductType::class,
        'status'                  => ProductStatus::class,
        'quality_criteria'        => 'array',
        'quality_responsibilities' => 'array',
        'baselined_at'            => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Product::class, 'parent_id');
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
        return $this->status === ProductStatus::Baselined;
    }
}
