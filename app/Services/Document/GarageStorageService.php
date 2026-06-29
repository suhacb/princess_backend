<?php

namespace App\Services\Document;

use App\Contracts\DocumentStorageDriver;
use App\Models\Project;
use DateTimeInterface;
use Illuminate\Support\Facades\Storage;

class GarageStorageService implements DocumentStorageDriver
{
    public function __construct(
        private readonly ProjectStorageService $projectStorage,
    ) {}

    public function put(Project $project, string $key, mixed $contents): void
    {
        $this->disk($project)->put($key, $contents);
    }

    public function get(Project $project, string $key): string
    {
        return $this->disk($project)->get($key);
    }

    public function temporaryUrl(Project $project, string $key, DateTimeInterface $expiry): string
    {
        $url = $this->disk($project)->temporaryUrl($key, $expiry);

        // Rewrite the host to the publicly reachable endpoint when running
        // behind Docker NAT (internal endpoint is unreachable from browsers).
        $publicEndpoint = config('princess.garage.public_endpoint');
        if ($publicEndpoint) {
            $internalEndpoint = rtrim(config('princess.garage.s3_endpoint'), '/');
            $url = str_replace($internalEndpoint, rtrim($publicEndpoint, '/'), $url);
        }

        return $url;
    }

    public function delete(Project $project, string $key): void
    {
        $this->disk($project)->delete($key);
    }

    public function exists(Project $project, string $key): bool
    {
        return $this->disk($project)->exists($key);
    }

    public function copy(Project $project, string $sourceKey, string $destKey): void
    {
        $this->disk($project)->copy($sourceKey, $destKey);
    }

    public function size(Project $project, string $key): int
    {
        return $this->disk($project)->size($key);
    }

    public function getFromTemplates(string $key): string
    {
        return $this->templatesDisk()->get($key);
    }

    protected function disk(Project $project): \Illuminate\Contracts\Filesystem\Cloud
    {
        return Storage::build([
            'driver'                  => 's3',
            'key'                     => config('princess.garage.access_key_id'),
            'secret'                  => config('princess.garage.secret_access_key'),
            'region'                  => config('princess.garage.region'),
            'bucket'                  => $this->projectStorage->bucketName($project),
            'endpoint'                => config('princess.garage.s3_endpoint'),
            'use_path_style_endpoint' => true,
            'throw'                   => true,
        ]);
    }

    protected function templatesDisk(): \Illuminate\Contracts\Filesystem\Cloud
    {
        return Storage::build([
            'driver'                  => 's3',
            'key'                     => config('princess.garage.access_key_id'),
            'secret'                  => config('princess.garage.secret_access_key'),
            'region'                  => config('princess.garage.region'),
            'bucket'                  => config('princess.garage.templates_bucket'),
            'endpoint'                => config('princess.garage.s3_endpoint'),
            'use_path_style_endpoint' => true,
            'throw'                   => true,
        ]);
    }
}
