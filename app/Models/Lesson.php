<?php

namespace App\Models;

use App\Enums\LessonSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'stage_id',
        'category',
        'description',
        'recommendation',
        'raised_by',
        'raised_at',
        'source',
    ];

    protected $casts = [
        'source'    => LessonSource::class,
        'raised_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'raised_by');
    }
}
