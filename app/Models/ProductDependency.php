<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDependency extends Model
{
    protected $fillable = [
        'project_id',
        'predecessor_id',
        'successor_id',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'predecessor_id');
    }

    public function successor(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'successor_id');
    }
}
