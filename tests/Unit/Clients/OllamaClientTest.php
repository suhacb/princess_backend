<?php

namespace Tests\Unit\Clients;

use App\Clients\OllamaClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaClientTest extends TestCase
{
    private OllamaClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new OllamaClient(
            baseUrl:    'http://localhost:11434',
            model:      'gemma4:e4b',
            modelFast:  'gemma4:e4b',
            modelSmart: 'gemma4:26b',
            timeout:    300,
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
}
