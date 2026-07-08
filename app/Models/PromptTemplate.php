<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'version',
        'body',
        'created_by',
        'active',
    ];

    protected $casts = [
        'version' => 'integer',
        'active'  => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * @return array<int, string>
     */
    public function placeholders(): array
    {
        preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $this->body, $matches);

        return array_values(array_unique($matches[1]));
    }
}
