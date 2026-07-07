<?php

namespace App\Clients;

use App\Classes\Llm\LlmResponse;
use App\Contracts\LlmClientContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TogetherAiClient implements LlmClientContract
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int    $timeout,
    ) {}

    public function ping(): bool
    {
        try {
            return Http::timeout(5)
                ->withToken($this->apiKey)
                ->get("{$this->baseUrl}/v1/models")
                ->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    public function chat(array $messages, array $options = []): LlmResponse
    {
        $model = $options['model'] ?? $this->model;
        $start = microtime(true);

        try {
            $response = Http::timeout($this->timeout)
                ->withToken($this->apiKey)
                ->post("{$this->baseUrl}/v1/chat/completions", [
                    'model'    => $model,
                    'messages' => $messages,
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Together AI chat request failed: {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException("Together AI chat request returned status {$response->status()}.");
        }

        $data = $response->json();

        return new LlmResponse(
            content:           $data['choices'][0]['message']['content'] ?? '',
            provider:          'together',
            model:             $model,
            promptTokens:      $data['usage']['prompt_tokens'] ?? null,
            completionTokens:  $data['usage']['completion_tokens'] ?? null,
            totalTokens:       $data['usage']['total_tokens'] ?? null,
            latencyMs:         (int) round((microtime(true) - $start) * 1000),
        );
    }

    public function generate(string $prompt, array $options = []): LlmResponse
    {
        return $this->chat([['role' => 'user', 'content' => $prompt]], $options);
    }
}
