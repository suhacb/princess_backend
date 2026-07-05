<?php

namespace Tests\Feature\Projects;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithRealGarage;
use Tests\TestCase;

/**
 * Regression coverage for #130: GarageStorageServiceTest mocks disk() for every
 * test, so it can never catch adapter-level failures against the real S3
 * backend. These tests provision a real bucket in the shared Garage instance
 * (namespaced under GARAGE_S3_BUCKET_PREFIX=princess-test-project so they
 * can't collide with dev or Playwright e2e buckets) and are torn down after
 * each test.
 */
class GarageStorageServiceRealBackendTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRealGarage;

    protected function tearDown(): void
    {
        $this->cleanUpRealGarageBuckets();

        parent::tearDown();
    }

    public function test_copy_against_real_garage_backend_does_not_throw(): void
    {
        $project = $this->provisionRealGarageProject();
        $service = $this->realGarageStorageService();

        $sourceKey = "documents/{$project->id}/versions/" . Str::uuid() . '/original.docx';
        $destKey = "documents/{$project->id}/versions/2/v1.docx";

        $service->put($project, $sourceKey, 'test-contents');

        $service->copy($project, $sourceKey, $destKey);

        $this->assertTrue($service->exists($project, $destKey));
        $this->assertSame('test-contents', $service->get($project, $destKey));
    }
}
