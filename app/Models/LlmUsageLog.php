<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LlmUsageLog extends Model
{
    protected $fillable = [
        'provider',
        'model',
        'tier',
        'caller',
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
}
