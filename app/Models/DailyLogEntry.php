<?php

namespace App\Models;

use App\Enums\DailyLogEntryType;
use App\Enums\DailyLogSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyLogEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'stage_id',
        'date',
        'entry_type',
        'body',
        'author_id',
        'source',
    ];

    protected $casts = [
        'date'       => 'date',
        'entry_type' => DailyLogEntryType::class,
        'source'     => DailyLogSource::class,
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'author_id');
    }
}
