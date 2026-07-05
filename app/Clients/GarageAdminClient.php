<?php

namespace App\Clients;

use App\Contracts\GarageAdminClientContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GarageAdminClient implements GarageAdminClientContract
{
    public function __construct(
        private readonly string $adminUrl,
        private readonly string $adminToken,
    ) {}

    public function ping(): bool
    {
        try {
            return Http::timeout(5)
                ->get("{$this->adminUrl}/health")
                ->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    public function getNodeId(): string
    {
        $status = $this->get('/v1/status');

        $nodes = $status['nodes'] ?? [];

        if (empty($nodes)) {
            throw new RuntimeException('Garage cluster has no nodes. Is the container running?');
        }

        return $nodes[0]['id'];
    }

    public function getLayoutVersion(): int
    {
        $layout = $this->get('/v1/layout');

        return (int) ($layout['version'] ?? 0);
    }

    public function nodeHasRole(string $nodeId): bool
    {
        $layout = $this->get('/v1/layout');

        foreach ($layout['roles'] ?? [] as $role) {
            if (($role['id'] ?? '') === $nodeId) {
                return true;
            }
        }

        return false;
    }

    public function applyLayout(string $nodeId, string $zone, int $capacityBytes): void
    {
        if ($this->nodeHasRole($nodeId)) {
            return;
        }

        $this->post('/v1/layout', [
            ['id' => $nodeId, 'zone' => $zone, 'capacity' => $capacityBytes, 'tags' => []],
        ]);

        $nextVersion = $this->getLayoutVersion() + 1;

        $this->post('/v1/layout/apply', ['version' => $nextVersion]);
    }

    public function createKey(string $name): array
    {
        $response = $this->post('/v1/key', ['name' => $name]);

        if (! isset($response['accessKeyId'], $response['secretAccessKey'])) {
            throw new RuntimeException("Garage key creation response missing credentials for '{$name}'.");
        }

        return [
            'accessKeyId'     => $response['accessKeyId'],
            'secretAccessKey' => $response['secretAccessKey'],
        ];
    }

    public function findKey(string $name): ?array
    {
        $keys = $this->get('/v1/key?list');

        foreach ($keys as $key) {
            if (($key['name'] ?? '') === $name) {
                // List endpoint returns 'id'; normalize to match the create-key response shape.
                return ['accessKeyId' => $key['id'], 'name' => $key['name']];
            }
        }

        return null;
    }

    public function createBucket(string $globalAlias): string
    {
        $response = $this->post('/v1/bucket', ['globalAlias' => $globalAlias]);

        if (! isset($response['id'])) {
            throw new RuntimeException("Garage bucket creation response missing ID for alias '{$globalAlias}'.");
        }

        return $response['id'];
    }

    public function findBucket(string $globalAlias): ?string
    {
        foreach ($this->listBuckets() as $bucket) {
            if (in_array($globalAlias, $bucket['globalAliases'] ?? [], strict: true)) {
                return $bucket['id'];
            }
        }

        return null;
    }

    public function allowKeyOnBucket(string $bucketId, string $keyId): void
    {
        $this->post('/v1/bucket/allow', [
            'bucketId'    => $bucketId,
            'accessKeyId' => $keyId,
            'permissions' => ['read' => true, 'write' => true, 'owner' => true],
        ]);
    }

    public function listBuckets(): array
    {
        return $this->get('/v1/bucket?list');
    }

    public function listBucketsWithPrefix(string $prefix): array
    {
        return array_values(array_filter(
            $this->listBuckets(),
            fn ($bucket) => collect($bucket['globalAliases'] ?? [])
                ->contains(fn ($alias) => str_starts_with($alias, $prefix)),
        ));
    }

    public function deleteBucket(string $bucketId): void
    {
        // Garage's admin API takes the bucket id as a query parameter on DELETE,
        // not a JSON body — passing it as a body silently 404s (unknown endpoint).
        $response = Http::timeout(10)
            ->withToken($this->adminToken)
            ->delete("{$this->adminUrl}/v1/bucket?id=" . urlencode($bucketId));

        if (! $response->successful()) {
            throw new RuntimeException(
                "Failed to delete Garage bucket '{$bucketId}': HTTP {$response->status()} — {$response->body()}"
            );
        }
    }

    private function get(string $path): array
    {
        $response = Http::timeout(10)->withToken($this->adminToken)->get("{$this->adminUrl}{$path}");

        if (! $response->successful()) {
            throw new RuntimeException(
                "Garage Admin API GET {$path} failed: HTTP {$response->status()} — {$response->body()}"
            );
        }

        return $response->json() ?? [];
    }

    private function post(string $path, array $body): array
    {
        $response = Http::timeout(10)->withToken($this->adminToken)->post("{$this->adminUrl}{$path}", $body);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Garage Admin API POST {$path} failed: HTTP {$response->status()} — {$response->body()}"
            );
        }

        return $response->json() ?? [];
    }
}
