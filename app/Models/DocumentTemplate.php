<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'parent_id',
        'name',
        'category',
        'type',
        's3_key',
        'settings',
        'created_by',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class, 'parent_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by');
    }
}
