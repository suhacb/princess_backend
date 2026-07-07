<?php

namespace App\Classes\Llm;

class LlmResponse
{
    public function __construct(
        public readonly string  $content,
        public readonly string  $provider,
        public readonly string  $model,
        public readonly ?int    $promptTokens     = null,
        public readonly ?int    $completionTokens = null,
        public readonly ?int    $totalTokens      = null,
        public readonly int     $latencyMs        = 0,
    ) {}
}
