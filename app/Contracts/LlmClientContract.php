<?php

namespace App\Contracts;

use App\Classes\Llm\LlmResponse;

interface LlmClientContract
{
    public function ping(): bool;

    /**
     * @param array<int, array{role: string, content: string}> $messages
     */
    public function chat(array $messages, array $options = []): LlmResponse;

    public function generate(string $prompt, array $options = []): LlmResponse;
}
