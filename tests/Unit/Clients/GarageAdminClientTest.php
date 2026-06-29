<?php

namespace Tests\Unit\Clients;

use App\Clients\GarageAdminClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GarageAdminClientTest extends TestCase
{
    private GarageAdminClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new GarageAdminClient(
            adminUrl:   'http://garage:3903',
            adminToken: 'test-token',
        );
    }

    public function test_ping_returns_true_when_health_endpoint_responds_ok(): void
    {
        Http::fake(['*/health' => Http::response([], 200)]);

        $this->assertTrue($this->client->ping());
    }

    public function test_ping_returns_false_when_health_endpoint_fails(): void
    {
        Http::fake(['*/health' => Http::response([], 503)]);

        $this->assertFalse($this->client->ping());
    }

    public function test_get_node_id_sends_bearer_token(): void
    {
        Http::fake(['*/v1/status' => Http::response([
            'nodes' => [['id' => 'node-abc']],
        ], 200)]);

        $nodeId = $this->client->getNodeId();

        $this->assertSame('node-abc', $nodeId);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_apply_layout_sends_bearer_token(): void
    {
        Http::fake([
            '*/v1/layout'       => Http::response(['version' => 0, 'roles' => []], 200),
            '*/v1/layout/apply' => Http::response([], 200),
        ]);

        $this->client->applyLayout('node-abc', 'garage', 1073741824);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_create_key_sends_bearer_token(): void
    {
        Http::fake(['*/v1/key' => Http::response([
            'accessKeyId'     => 'GKtest',
            'secretAccessKey' => 'sec',
        ], 200)]);

        $this->client->createKey('princess-backend');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_create_bucket_sends_bearer_token(): void
    {
        Http::fake(['*/v1/bucket' => Http::response(['id' => 'bucket-xyz'], 200)]);

        $this->client->createBucket('princess-templates');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-token'));
    }
}
