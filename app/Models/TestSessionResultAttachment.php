<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestSessionResultAttachment extends Model
{
    use HasFactory;

    // Attachments are immutable once uploaded — only created_at is tracked.
    const UPDATED_AT = null;

    protected $fillable = [
        'test_session_result_id',
        'step_index',
        's3_key',
        'file_name',
        'file_size_bytes',
        'mime_type',
        'created_by',
    ];

    protected $casts = [
        'step_index'      => 'integer',
        'file_size_bytes' => 'integer',
        'created_at'      => 'datetime',
    ];

    public function testSessionResult(): BelongsTo
    {
        return $this->belongsTo(TestSessionResult::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by');
    }
}
