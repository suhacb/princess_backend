<?php

namespace Tests\Unit\Clients;

use App\Clients\OllamaClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OllamaClientTest extends TestCase
{
    private OllamaClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new OllamaClient(
            baseUrl: 'http://localhost:11434',
            model:   'gemma4:e4b',
            timeout: 300,
        );
    }

    public function test_ping_returns_true_when_tags_endpoint_responds_ok(): void
    {
        Http::fake(['*/api/tags' => Http::response(['models' => []], 200)]);

        $this->assertTrue($this->client->ping());
    }

    public function test_ping_returns_false_when_tags_endpoint_fails(): void
    {
        Http::fake(['*/api/tags' => Http::response([], 500)]);

        $this->assertFalse($this->client->ping());
    }

    public function test_ping_returns_false_on_connection_exception(): void
    {
        Http::fake(['*/api/tags' => fn () => throw new ConnectionException('refused')]);

        $this->assertFalse($this->client->ping());
    }

    public function test_chat_sends_messages_to_ollama_chat_endpoint_with_default_model(): void
    {
        Http::fake(['*/api/chat' => Http::response([
            'message'           => ['role' => 'assistant', 'content' => 'hello there'],
            'prompt_eval_count' => 10,
            'eval_count'        => 5,
        ], 200)]);

        $response = $this->client->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('hello there', $response->content);
        $this->assertSame('ollama', $response->provider);
        $this->assertSame('gemma4:e4b', $response->model);
        $this->assertSame(10, $response->promptTokens);
        $this->assertSame(5, $response->completionTokens);
        $this->assertSame(15, $response->totalTokens);

        Http::assertSent(fn ($request) => $request['model'] === 'gemma4:e4b'
            && $request['stream'] === false
            && $request['messages'] === [['role' => 'user', 'content' => 'hi']]);
    }

    public function test_chat_uses_model_override_from_options(): void
    {
        Http::fake(['*/api/chat' => Http::response(['message' => ['content' => 'ok']], 200)]);

        $response = $this->client->chat([['role' => 'user', 'content' => 'hi']], ['model' => 'gemma4:26b']);

        $this->assertSame('gemma4:26b', $response->model);
        Http::assertSent(fn ($request) => $request['model'] === 'gemma4:26b');
    }

    public function test_chat_throws_when_response_is_not_successful(): void
    {
        Http::fake(['*/api/chat' => Http::response([], 500)]);

        $this->expectException(RuntimeException::class);

        $this->client->chat([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_chat_throws_on_connection_exception(): void
    {
        Http::fake(['*/api/chat' => fn () => throw new ConnectionException('refused')]);

        $this->expectException(RuntimeException::class);

        $this->client->chat([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_generate_wraps_prompt_as_single_user_message(): void
    {
        Http::fake(['*/api/chat' => Http::response(['message' => ['content' => 'ok']], 200)]);

        $this->client->generate('hi there');

        Http::assertSent(fn ($request) => $request['messages'] === [['role' => 'user', 'content' => 'hi there']]);
    }
}
