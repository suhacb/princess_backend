<?php

namespace App\Models;

use App\Traits\IsAuditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends Model
{
    use HasFactory, SoftDeletes, IsAuditable;

    protected array $auditableFields = ['title', 'date_time', 'agenda', 'minutes_body'];

    protected static function booted(): void
    {
        static::deleting(function (Meeting $meeting) {
            $meeting->actionItems()->delete();
        });
    }

    protected $fillable = [
        'project_id',
        'title',
        'date_time',
        'agenda',
        'minutes_body',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'meeting_attendees');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(MeetingActionItem::class);
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
