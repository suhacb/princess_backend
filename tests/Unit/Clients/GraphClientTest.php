<?php

namespace Tests\Unit\Clients;

use App\Clients\GraphClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GraphClientTest extends TestCase
{
    private GraphClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new GraphClient(tenantId: 'tenant', clientId: 'client', clientSecret: 'secret');
    }

    public function test_ping_returns_true_when_graph_endpoint_is_reachable(): void
    {
        Http::fake(['graph.microsoft.com/*' => Http::response([], 200)]);

        $this->assertTrue($this->client->ping());
    }

    public function test_ping_returns_true_even_when_graph_returns_401(): void
    {
        Http::fake(['graph.microsoft.com/*' => Http::response([], 401)]);

        $this->assertTrue($this->client->ping());
    }

    public function test_ping_returns_false_on_connection_exception(): void
    {
        Http::fake(['graph.microsoft.com/*' => fn () => throw new ConnectionException('refused')]);

        $this->assertFalse($this->client->ping());
    }
}
