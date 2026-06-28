<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Services\Document\GarageStorageService;
use App\Services\Document\ProjectStorageService;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GarageStorageServiceTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;
    private \Mockery\MockInterface $disk;
    private GarageStorageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->create();

        $this->mock(ProjectStorageService::class)
            ->shouldReceive('bucketName')
            ->andReturn('princess-test-project-1');

        $this->disk = Mockery::mock(Cloud::class);

        // Partial mock: use real methods but override disk() to return our fake.
        $this->service = Mockery::mock(GarageStorageService::class, [app(ProjectStorageService::class)])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $this->service->shouldReceive('disk')->andReturn($this->disk);
    }

    public function test_put_delegates_to_disk(): void
    {
        $this->disk->shouldReceive('put')->with('documents/1/versions/1/original.docx', 'file-contents')->once();

        $this->service->put($this->project, 'documents/1/versions/1/original.docx', 'file-contents');
    }

    public function test_get_delegates_to_disk(): void
    {
        $this->disk->shouldReceive('get')->with('documents/1/versions/1/original.docx')->andReturn('file-contents');

        $this->assertSame('file-contents', $this->service->get($this->project, 'documents/1/versions/1/original.docx'));
    }

    public function test_exists_delegates_to_disk(): void
    {
        $this->disk->shouldReceive('exists')->with('some/key.docx')->andReturn(true);

        $this->assertTrue($this->service->exists($this->project, 'some/key.docx'));
    }

    public function test_delete_delegates_to_disk(): void
    {
        $this->disk->shouldReceive('delete')->with('some/key.docx')->once();

        $this->service->delete($this->project, 'some/key.docx');
    }

    public function test_copy_delegates_to_disk(): void
    {
        $this->disk->shouldReceive('copy')->with('src/key.docx', 'dst/key.docx')->once();

        $this->service->copy($this->project, 'src/key.docx', 'dst/key.docx');
    }

    public function test_size_delegates_to_disk(): void
    {
        $this->disk->shouldReceive('size')->with('some/key.docx')->andReturn(1024);

        $this->assertSame(1024, $this->service->size($this->project, 'some/key.docx'));
    }

    public function test_temporary_url_rewrites_internal_endpoint_to_public(): void
    {
        config(['princess.garage.s3_endpoint'   => 'http://garage:3900']);
        config(['princess.garage.public_endpoint' => 'http://localhost:10110']);

        $this->disk->shouldReceive('temporaryUrl')
            ->andReturn('http://garage:3900/princess-test-project-1/some/key.docx?X-Amz-Signature=abc');

        $url = $this->service->temporaryUrl($this->project, 'some/key.docx', now()->addMinutes(5));

        $this->assertStringContainsString('http://localhost:10110', $url);
        $this->assertStringNotContainsString('http://garage:3900', $url);
    }

    public function test_temporary_url_leaves_url_unchanged_when_no_public_endpoint(): void
    {
        config(['princess.garage.public_endpoint' => null]);

        $raw = 'http://garage:3900/princess-test-project-1/some/key.docx?X-Amz-Signature=abc';
        $this->disk->shouldReceive('temporaryUrl')->andReturn($raw);

        $url = $this->service->temporaryUrl($this->project, 'some/key.docx', now()->addMinutes(5));

        $this->assertSame($raw, $url);
    }
}
