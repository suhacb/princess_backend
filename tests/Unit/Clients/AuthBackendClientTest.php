<?php

namespace Tests\Unit\Clients;

use App\Clients\AuthBackendClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthBackendClientTest extends TestCase
{
    private AuthBackendClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new AuthBackendClient(baseUrl: 'http://localhost', port: 9025);
    }

    public function test_ping_returns_true_when_health_endpoint_responds_ok(): void
    {
        Http::fake(['*/api/health' => Http::response([], 200)]);

        $this->assertTrue($this->client->ping());
    }

    public function test_ping_returns_false_when_health_endpoint_fails(): void
    {
        Http::fake(['*/api/health' => Http::response([], 500)]);

        $this->assertFalse($this->client->ping());
    }

    public function test_ping_returns_false_on_connection_exception(): void
    {
        Http::fake(['*/api/health' => fn () => throw new ConnectionException('refused')]);

        $this->assertFalse($this->client->ping());
    }
}
