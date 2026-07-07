<?php

namespace App\Services\Llm;

use App\Classes\Llm\LlmResponse;
use App\Contracts\LlmClientContract;
use App\Models\LlmUsageLog;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class LlmRouterService
{
    /**
     * @param array<string, LlmClientContract> $providers
     * @param array<string, array<int, array{provider: string, model: string}>> $tiers
     */
    public function __construct(
        private readonly array $providers,
        private readonly array $tiers,
        private readonly string $defaultTier,
    ) {}

    /**
     * @param array<int, array{role: string, content: string}> $messages
     */
    public function chat(?string $tier, array $messages, array $options = [], ?string $caller = null): LlmResponse
    {
        $tier ??= $this->defaultTier;
        $chain = $this->tiers[$tier] ?? null;

        if ($chain === null) {
            throw new InvalidArgumentException("Unknown LLM tier [{$tier}].");
        }

        $lastException = null;

        foreach ($chain as $candidate) {
            $provider = $this->providers[$candidate['provider']] ?? null;

            if ($provider === null) {
                continue;
            }

            try {
                $response = $provider->chat($messages, [...$options, 'model' => $candidate['model']]);

                $this->logUsage($candidate['provider'], $candidate['model'], $tier, $caller, $response, success: true);

                return $response;
            } catch (Throwable $e) {
                $this->logUsage($candidate['provider'], $candidate['model'], $tier, $caller, null, success: false, error: $e->getMessage());

                $lastException = $e;
            }
        }

        throw new RuntimeException("All providers failed for LLM tier [{$tier}].", previous: $lastException);
    }

    public function generate(?string $tier, string $prompt, array $options = [], ?string $caller = null): LlmResponse
    {
        return $this->chat($tier, [['role' => 'user', 'content' => $prompt]], $options, $caller);
    }

    private function logUsage(
        string $provider,
        string $model,
        string $tier,
        ?string $caller,
        ?LlmResponse $response,
        bool $success,
        ?string $error = null,
    ): void {
        LlmUsageLog::create([
            'provider'          => $provider,
            'model'             => $model,
            'tier'              => $tier,
            'caller'            => $caller,
            'prompt_tokens'     => $response?->promptTokens,
            'completion_tokens' => $response?->completionTokens,
            'total_tokens'      => $response?->totalTokens,
            'latency_ms'        => $response?->latencyMs ?? 0,
            'success'           => $success,
            'error_message'     => $error,
        ]);
    }
}
