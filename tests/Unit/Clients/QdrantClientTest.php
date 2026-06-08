<?php

namespace Tests\Unit\Clients;

use App\Clients\QdrantClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QdrantClientTest extends TestCase
{
    private QdrantClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new QdrantClient(baseUrl: 'http://localhost:6333');
    }

    public function test_ping_returns_true_when_healthz_responds_ok(): void
    {
        Http::fake(['*/healthz' => Http::response(['title' => 'qdrant'], 200)]);

        $this->assertTrue($this->client->ping());
    }

    public function test_ping_returns_false_when_healthz_fails(): void
    {
        Http::fake(['*/healthz' => Http::response([], 503)]);

        $this->assertFalse($this->client->ping());
    }

    public function test_ping_returns_false_on_connection_exception(): void
    {
        Http::fake(['*/healthz' => fn () => throw new ConnectionException('refused')]);

        $this->assertFalse($this->client->ping());
    }
}
