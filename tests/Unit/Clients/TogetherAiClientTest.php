<?php

namespace Tests\Unit\Clients;

use App\Clients\TogetherAiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class TogetherAiClientTest extends TestCase
{
    private TogetherAiClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new TogetherAiClient(
            baseUrl: 'https://api.together.xyz',
            apiKey:  'secret-key',
            model:   'meta-llama/Llama-3.3-70B-Instruct-Turbo',
            timeout: 120,
        );
    }

    public function test_ping_returns_true_when_models_endpoint_responds_ok(): void
    {
        Http::fake(['*/v1/models' => Http::response([], 200)]);

        $this->assertTrue($this->client->ping());

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret-key'));
    }

    public function test_ping_returns_false_when_models_endpoint_fails(): void
    {
        Http::fake(['*/v1/models' => Http::response([], 401)]);

        $this->assertFalse($this->client->ping());
    }

    public function test_ping_returns_false_on_connection_exception(): void
    {
        Http::fake(['*/v1/models' => fn () => throw new ConnectionException('refused')]);

        $this->assertFalse($this->client->ping());
    }

    public function test_chat_sends_messages_to_chat_completions_endpoint_with_default_model(): void
    {
        Http::fake(['*/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'hello there']]],
            'usage'   => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ], 200)]);

        $response = $this->client->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('hello there', $response->content);
        $this->assertSame('together', $response->provider);
        $this->assertSame('meta-llama/Llama-3.3-70B-Instruct-Turbo', $response->model);
        $this->assertSame(10, $response->promptTokens);
        $this->assertSame(5, $response->completionTokens);
        $this->assertSame(15, $response->totalTokens);

        Http::assertSent(fn ($request) => $request['model'] === 'meta-llama/Llama-3.3-70B-Instruct-Turbo'
            && $request->hasHeader('Authorization', 'Bearer secret-key'));
    }

    public function test_chat_uses_model_override_from_options(): void
    {
        Http::fake(['*/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'ok']]],
        ], 200)]);

        $response = $this->client->chat([['role' => 'user', 'content' => 'hi']], ['model' => 'deepseek-ai/DeepSeek-V3']);

        $this->assertSame('deepseek-ai/DeepSeek-V3', $response->model);
        Http::assertSent(fn ($request) => $request['model'] === 'deepseek-ai/DeepSeek-V3');
    }

    public function test_chat_throws_when_response_is_not_successful(): void
    {
        Http::fake(['*/v1/chat/completions' => Http::response([], 500)]);

        $this->expectException(RuntimeException::class);

        $this->client->chat([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_chat_throws_on_connection_exception(): void
    {
        Http::fake(['*/v1/chat/completions' => fn () => throw new ConnectionException('refused')]);

        $this->expectException(RuntimeException::class);

        $this->client->chat([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_generate_wraps_prompt_as_single_user_message(): void
    {
        Http::fake(['*/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'ok']]],
        ], 200)]);

        $this->client->generate('hi there');

        Http::assertSent(fn ($request) => $request['messages'] === [['role' => 'user', 'content' => 'hi there']]);
    }
}
