<?php

namespace App\Models;

use App\Enums\MeetingActionItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingActionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'owner_id',
        'description',
        'due_date',
        'status',
    ];

    protected $casts = [
        'status'   => MeetingActionItemStatus::class,
        'due_date' => 'date',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'owner_id');
    }
}
