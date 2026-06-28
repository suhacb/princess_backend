<?php

namespace Tests\Feature\Projects;

use App\Contracts\GarageAdminClientContract;
use App\Models\Project;
use App\Services\Document\ProjectStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectStorageServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProjectStorageService $service;
    private \Mockery\MockInterface $garage;

    protected function setUp(): void
    {
        parent::setUp();
        config(['princess.garage.access_key_id' => 'test-access-key']);
        $this->garage  = $this->mock(GarageAdminClientContract::class);
        $this->service = app(ProjectStorageService::class);
    }

    public function test_bucket_name_uses_configured_prefix_and_project_id(): void
    {
        $project = Project::factory()->create();

        $expected = config('princess.garage.bucket_prefix') . '-' . $project->id;

        $this->assertSame($expected, $this->service->bucketName($project));
    }

    public function test_provision_creates_bucket_and_grants_key_access(): void
    {
        $project  = Project::factory()->create();
        $name     = $this->service->bucketName($project);
        $bucketId = 'bucket-abc';

        $this->garage->shouldReceive('findBucket')->with($name)->andReturn(null);
        $this->garage->shouldReceive('createBucket')->with($name)->andReturn($bucketId);
        $this->garage->shouldReceive('allowKeyOnBucket')
            ->with($bucketId, config('princess.garage.access_key_id'))
            ->once();

        $this->service->provision($project);
    }

    public function test_provision_skips_creation_when_bucket_already_exists(): void
    {
        $project  = Project::factory()->create();
        $name     = $this->service->bucketName($project);
        $bucketId = 'existing-bucket';

        $this->garage->shouldReceive('findBucket')->with($name)->andReturn($bucketId);
        $this->garage->shouldReceive('createBucket')->never();
        $this->garage->shouldReceive('allowKeyOnBucket')
            ->with($bucketId, config('princess.garage.access_key_id'))
            ->once();

        $this->service->provision($project);
    }

    public function test_provision_propagates_garage_exceptions(): void
    {
        $project = Project::factory()->create();

        $this->garage->shouldReceive('findBucket')->andThrow(new \RuntimeException('Garage down'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Garage down');

        $this->service->provision($project);
    }

    public function test_archive_is_a_no_op(): void
    {
        $project = Project::factory()->create();

        // No Garage calls expected.
        $this->garage->shouldNotReceive('deleteBucket');
        $this->garage->shouldNotReceive('findBucket');

        $this->service->archive($project);
    }
}
