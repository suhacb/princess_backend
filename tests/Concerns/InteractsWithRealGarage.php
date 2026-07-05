<?php

namespace Tests\Concerns;

use App\Contracts\GarageAdminClientContract;
use App\Models\Project;
use App\Services\Document\GarageStorageService;
use App\Services\Document\ProjectStorageService;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Provisions and tears down real buckets in the shared Garage instance for
 * tests that need to exercise the actual S3 adapter (e.g. copy()/ACL
 * behaviour that a mocked disk can't catch). Bucket names are namespaced
 * under GARAGE_S3_BUCKET_PREFIX (princess-test-project by default), which is
 * distinct from dev (princess-project) and Playwright e2e
 * (princess-e2e-project) buckets, and deleteRealGarageBucket() refuses to
 * touch anything outside that prefix.
 */
trait InteractsWithRealGarage
{
    private array $realGarageBucketIds = [];

    protected function realGarageAdminClient(): GarageAdminClientContract
    {
        return app(GarageAdminClientContract::class);
    }

    /**
     * Creates a throwaway project + real bucket in Garage, granting the
     * backend key access. Returns the project; the bucket is deleted
     * automatically in tearDown().
     */
    protected function provisionRealGarageProject(): Project
    {
        $project = Project::factory()->create();

        $projectStorage = new ProjectStorageService($this->realGarageAdminClient());
        $projectStorage->provision($project);

        $this->realGarageBucketIds[] = $projectStorage->bucketName($project);

        return $project;
    }

    protected function realGarageStorageService(): GarageStorageService
    {
        return new GarageStorageService(new ProjectStorageService($this->realGarageAdminClient()));
    }

    /** @internal registered by tests via $this->beforeApplicationDestroyed or called directly in a finally block */
    protected function cleanUpRealGarageBuckets(): void
    {
        $garage = $this->realGarageAdminClient();
        $prefix = config('princess.garage.bucket_prefix');

        if (! str_starts_with($prefix, 'princess-test-project')) {
            throw new RuntimeException(
                "Refusing to clean up Garage buckets: GARAGE_S3_BUCKET_PREFIX is '{$prefix}', ".
                "expected the PHPUnit-only prefix 'princess-test-project'. Check .testing.env."
            );
        }

        foreach ($this->realGarageBucketIds as $alias) {
            $bucketId = $garage->findBucket($alias);

            if ($bucketId === null) {
                continue;
            }

            $disk = $this->diskForAlias($alias);

            foreach ($disk->allFiles() as $file) {
                $disk->delete($file);
            }

            $garage->deleteBucket($bucketId);
        }

        $this->realGarageBucketIds = [];
    }

    private function diskForAlias(string $alias): Cloud
    {
        return Storage::build([
            'driver'                  => 's3',
            'key'                     => config('princess.garage.access_key_id'),
            'secret'                  => config('princess.garage.secret_access_key'),
            'region'                  => config('princess.garage.region'),
            'bucket'                  => $alias,
            'endpoint'                => config('princess.garage.s3_endpoint'),
            'use_path_style_endpoint' => true,
            'throw'                   => false,
        ]);
    }
}
