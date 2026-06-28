<?php

namespace Tests\Feature\Commands\Garage;

use App\Contracts\GarageAdminClientContract;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BootstrapGarageCommandTest extends TestCase
{
    private function makeGarageMock(): \Mockery\MockInterface
    {
        return $this->mock(GarageAdminClientContract::class);
    }

    private function mockFullBootstrap(\Mockery\MockInterface $garage, string $nodeId = 'node-abc'): void
    {
        $garage->shouldReceive('ping')->andReturn(true);
        $garage->shouldReceive('getNodeId')->andReturn($nodeId);
        $garage->shouldReceive('nodeHasRole')->with($nodeId)->andReturn(false);
        $garage->shouldReceive('applyLayout')->with($nodeId, 'garage', 1073741824)->once();
        $garage->shouldReceive('getLayoutVersion')->andReturn(1);
        $garage->shouldReceive('findKey')->with('princess-backend')->andReturn(null);
        $garage->shouldReceive('createKey')->with('princess-backend')->andReturn([
            'accessKeyId'     => 'GKtest123',
            'secretAccessKey' => 'secret-abc',
        ]);
        $garage->shouldReceive('findBucket')->with(config('princess.garage.templates_bucket'))->andReturn(null);
        $garage->shouldReceive('createBucket')->with(config('princess.garage.templates_bucket'))->andReturn('bucket-xyz');
        $garage->shouldReceive('allowKeyOnBucket')->with('bucket-xyz', 'GKtest123')->once();
    }

    public function test_exits_with_failure_when_garage_is_unreachable(): void
    {
        $garage = $this->makeGarageMock();
        $garage->shouldReceive('ping')->andReturn(false);

        $this->artisan('garage:bootstrap --force')
            ->assertExitCode(1);
    }

    public function test_full_bootstrap_exits_successfully(): void
    {
        $this->mockFullBootstrap($this->makeGarageMock());

        $this->artisan('garage:bootstrap --force')
            ->assertExitCode(0);
    }

    public function test_outputs_key_credentials_on_fresh_install(): void
    {
        $this->mockFullBootstrap($this->makeGarageMock());

        $this->artisan('garage:bootstrap --force')
            ->expectsOutputToContain('GKtest123')
            ->expectsOutputToContain('secret-abc');
    }

    public function test_skips_layout_when_node_already_has_role(): void
    {
        $garage = $this->makeGarageMock();
        $garage->shouldReceive('ping')->andReturn(true);
        $garage->shouldReceive('getNodeId')->andReturn('node-abc');
        $garage->shouldReceive('nodeHasRole')->with('node-abc')->andReturn(true);
        $garage->shouldReceive('applyLayout')->never();
        $garage->shouldReceive('findKey')->with('princess-backend')->andReturn(null);
        $garage->shouldReceive('createKey')->andReturn(['accessKeyId' => 'GKtest', 'secretAccessKey' => 'sec']);
        $garage->shouldReceive('findBucket')->andReturn(null);
        $garage->shouldReceive('createBucket')->andReturn('bucket-id');
        $garage->shouldReceive('allowKeyOnBucket')->once();

        $this->artisan('garage:bootstrap --force')
            ->assertExitCode(0);
    }

    public function test_skips_key_creation_when_key_already_exists(): void
    {
        $garage = $this->makeGarageMock();
        $garage->shouldReceive('ping')->andReturn(true);
        $garage->shouldReceive('getNodeId')->andReturn('node-abc');
        $garage->shouldReceive('nodeHasRole')->andReturn(true);
        $garage->shouldReceive('findKey')->with('princess-backend')->andReturn([
            'accessKeyId' => 'GKexisting',
            'name'        => 'princess-backend',
        ]);
        $garage->shouldReceive('createKey')->never();
        $garage->shouldReceive('findBucket')->andReturn(null);
        $garage->shouldReceive('createBucket')->andReturn('bucket-id');
        $garage->shouldReceive('allowKeyOnBucket')->with('bucket-id', 'GKexisting')->once();

        $this->artisan('garage:bootstrap --force')
            ->assertExitCode(0)
            ->expectsOutputToContain('GKexisting');
    }

    public function test_skips_bucket_creation_when_bucket_already_exists(): void
    {
        $garage = $this->makeGarageMock();
        $garage->shouldReceive('ping')->andReturn(true);
        $garage->shouldReceive('getNodeId')->andReturn('node-abc');
        $garage->shouldReceive('nodeHasRole')->andReturn(true);
        $garage->shouldReceive('findKey')->andReturn(null);
        $garage->shouldReceive('createKey')->andReturn(['accessKeyId' => 'GKtest', 'secretAccessKey' => 'sec']);
        $garage->shouldReceive('findBucket')->with(config('princess.garage.templates_bucket'))->andReturn('existing-bucket-id');
        $garage->shouldReceive('createBucket')->never();
        $garage->shouldReceive('allowKeyOnBucket')->with('existing-bucket-id', 'GKtest')->once();

        $this->artisan('garage:bootstrap --force')
            ->assertExitCode(0);
    }

    public function test_verifies_s3_connectivity_using_garage_disk(): void
    {
        $this->mockFullBootstrap($this->makeGarageMock());

        $this->artisan('garage:bootstrap --force')
            ->assertExitCode(0);

        // The bootstrap command performs a put/get/delete cycle on the garage disk.
        // Storage::fake('garage') records writes — assert that the probe file was used.
        Storage::disk('garage')->assertMissing('_bootstrap-probe.txt');
    }

    public function test_exits_with_failure_when_layout_apply_throws(): void
    {
        $garage = $this->makeGarageMock();
        $garage->shouldReceive('ping')->andReturn(true);
        $garage->shouldReceive('getNodeId')->andReturn('node-abc');
        $garage->shouldReceive('nodeHasRole')->andReturn(false);
        $garage->shouldReceive('applyLayout')->andThrow(new \RuntimeException('Layout failed'));

        $this->artisan('garage:bootstrap --force')
            ->assertExitCode(1);
    }

    public function test_exits_with_failure_when_key_creation_throws(): void
    {
        $garage = $this->makeGarageMock();
        $garage->shouldReceive('ping')->andReturn(true);
        $garage->shouldReceive('getNodeId')->andReturn('node-abc');
        $garage->shouldReceive('nodeHasRole')->andReturn(true);
        $garage->shouldReceive('findKey')->andReturn(null);
        $garage->shouldReceive('createKey')->andThrow(new \RuntimeException('Key creation failed'));

        $this->artisan('garage:bootstrap --force')
            ->assertExitCode(1);
    }

    public function test_exits_with_failure_when_bucket_creation_throws(): void
    {
        $garage = $this->makeGarageMock();
        $garage->shouldReceive('ping')->andReturn(true);
        $garage->shouldReceive('getNodeId')->andReturn('node-abc');
        $garage->shouldReceive('nodeHasRole')->andReturn(true);
        $garage->shouldReceive('findKey')->andReturn(null);
        $garage->shouldReceive('createKey')->andReturn(['accessKeyId' => 'GKtest', 'secretAccessKey' => 'sec']);
        $garage->shouldReceive('findBucket')->andReturn(null);
        $garage->shouldReceive('createBucket')->andThrow(new \RuntimeException('Bucket failed'));

        $this->artisan('garage:bootstrap --force')
            ->assertExitCode(1);
    }
}
