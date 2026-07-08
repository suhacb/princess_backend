<?php

namespace Tests\Unit\Services\Llm;

use App\Classes\Llm\LlmResponse;
use App\Contracts\LlmClientContract;
use App\Models\LlmUsageLog;
use App\Services\Llm\LlmRouterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class LlmRouterServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fakeProvider(?LlmResponse $response, ?\Throwable $throws = null): LlmClientContract
    {
        return new class($response, $throws) implements LlmClientContract {
            public function __construct(
                private readonly ?LlmResponse $response,
                private readonly ?\Throwable $throws,
            ) {}

            public function ping(): bool
            {
                return $this->throws === null;
            }

            public function chat(array $messages, array $options = []): LlmResponse
            {
                if ($this->throws) {
                    throw $this->throws;
                }

                return $this->response;
            }

            public function generate(string $prompt, array $options = []): LlmResponse
            {
                return $this->chat([['role' => 'user', 'content' => $prompt]], $options);
            }
        };
    }

    private function tiers(): array
    {
        return [
            'fast' => [
                ['provider' => 'ollama', 'model' => 'gemma4:e4b'],
                ['provider' => 'together', 'model' => 'meta-llama/Llama-3.3-70B-Instruct-Turbo'],
            ],
        ];
    }

    public function test_chat_returns_response_from_primary_provider_when_it_succeeds(): void
    {
        $response = new LlmResponse(content: 'ok', provider: 'ollama', model: 'gemma4:e4b', latencyMs: 10);

        $router = new LlmRouterService(
            providers: [
                'ollama'   => $this->fakeProvider($response),
                'together' => $this->fakeProvider(null, new RuntimeException('should not be called')),
            ],
            tiers: $this->tiers(),
            defaultTier: 'fast',
        );

        $result = $router->chat('fast', [['role' => 'user', 'content' => 'hi']], caller: 'test-caller');

        $this->assertSame('ok', $result->content);
        $this->assertDatabaseHas('llm_usage_logs', [
            'provider' => 'ollama',
            'model'    => 'gemma4:e4b',
            'tier'     => 'fast',
            'caller'   => 'test-caller',
            'success'  => true,
        ]);
    }

    public function test_chat_falls_back_to_next_provider_when_primary_fails(): void
    {
        $fallbackResponse = new LlmResponse(content: 'fallback ok', provider: 'together', model: 'meta-llama/Llama-3.3-70B-Instruct-Turbo', latencyMs: 20);

        $router = new LlmRouterService(
            providers: [
                'ollama'   => $this->fakeProvider(null, new RuntimeException('ollama down')),
                'together' => $this->fakeProvider($fallbackResponse),
            ],
            tiers: $this->tiers(),
            defaultTier: 'fast',
        );

        $result = $router->chat('fast', [['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('fallback ok', $result->content);
        $this->assertDatabaseHas('llm_usage_logs', ['provider' => 'ollama', 'success' => false, 'error_message' => 'ollama down']);
        $this->assertDatabaseHas('llm_usage_logs', ['provider' => 'together', 'success' => true]);
    }

    public function test_chat_throws_when_all_providers_in_chain_fail(): void
    {
        $router = new LlmRouterService(
            providers: [
                'ollama'   => $this->fakeProvider(null, new RuntimeException('ollama down')),
                'together' => $this->fakeProvider(null, new RuntimeException('together down')),
            ],
            tiers: $this->tiers(),
            defaultTier: 'fast',
        );

        $this->expectException(RuntimeException::class);

        $router->chat('fast', [['role' => 'user', 'content' => 'hi']]);
    }

    public function test_chat_throws_for_unknown_tier(): void
    {
        $router = new LlmRouterService(providers: [], tiers: $this->tiers(), defaultTier: 'fast');

        $this->expectException(InvalidArgumentException::class);

        $router->chat('reasoning', [['role' => 'user', 'content' => 'hi']]);
    }

    public function test_chat_skips_tier_entry_whose_provider_is_not_registered(): void
    {
        $response = new LlmResponse(content: 'from together', provider: 'together', model: 'meta-llama/Llama-3.3-70B-Instruct-Turbo', latencyMs: 8);

        $tiers = [
            'fast' => [
                ['provider' => 'unregistered', 'model' => 'ghost-model'],
                ['provider' => 'together', 'model' => 'meta-llama/Llama-3.3-70B-Instruct-Turbo'],
            ],
        ];

        $router = new LlmRouterService(
            providers: ['together' => $this->fakeProvider($response)],
            tiers: $tiers,
            defaultTier: 'fast',
        );

        $result = $router->chat('fast', [['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('from together', $result->content);
        $this->assertDatabaseMissing('llm_usage_logs', ['provider' => 'unregistered']);
    }

    public function test_chat_falls_back_to_default_tier_when_none_given(): void
    {
        $response = new LlmResponse(content: 'ok', provider: 'ollama', model: 'gemma4:e4b', latencyMs: 5);

        $router = new LlmRouterService(
            providers: ['ollama' => $this->fakeProvider($response)],
            tiers: $this->tiers(),
            defaultTier: 'fast',
        );

        $result = $router->chat(null, [['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('ok', $result->content);
        $this->assertDatabaseHas('llm_usage_logs', ['tier' => 'fast']);
    }

    public function test_chat_persists_prompt_template_id_on_usage_log_when_provided(): void
    {
        $person = \App\Models\Person::factory()->create();
        $template = \App\Models\PromptTemplate::factory()->create(['created_by' => $person->id]);

        $response = new LlmResponse(content: 'ok', provider: 'ollama', model: 'gemma4:e4b', latencyMs: 10);

        $router = new LlmRouterService(
            providers: ['ollama' => $this->fakeProvider($response)],
            tiers: $this->tiers(),
            defaultTier: 'fast',
        );

        $router->chat('fast', [['role' => 'user', 'content' => 'hi']], promptTemplateId: $template->id);

        $this->assertDatabaseHas('llm_usage_logs', [
            'provider'           => 'ollama',
            'prompt_template_id' => $template->id,
        ]);
    }

    public function test_generate_wraps_prompt_and_delegates_to_chat(): void
    {
        $response = new LlmResponse(content: 'ok', provider: 'ollama', model: 'gemma4:e4b', latencyMs: 5);

        $router = new LlmRouterService(
            providers: ['ollama' => $this->fakeProvider($response)],
            tiers: $this->tiers(),
            defaultTier: 'fast',
        );

        $result = $router->generate('fast', 'hi there');

        $this->assertSame('ok', $result->content);
    }
}
