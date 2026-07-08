<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmUsageLog extends Model
{
    protected $fillable = [
        'provider',
        'model',
        'tier',
        'caller',
        'prompt_template_id',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'latency_ms',
        'success',
        'error_message',
    ];

    protected $casts = [
        'success' => 'boolean',
    ];

    public function promptTemplate(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class);
    }
}
