<?php

namespace Tests\Feature\Commands;

use App\Contracts\AuthGatewayClientContract;
use App\Contracts\GraphClientContract;
use App\Contracts\OllamaClientContract;
use App\Contracts\QdrantClientContract;
use App\Contracts\ZincSearchClientContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class HealthCheckCommandTest extends TestCase
{
    private function mockAllExternalServices(bool $healthy): void
    {
        foreach ([
            AuthGatewayClientContract::class,
            GraphClientContract::class,
            OllamaClientContract::class,
            QdrantClientContract::class,
            ZincSearchClientContract::class,
        ] as $contract) {
            $this->mock($contract)
                ->shouldReceive('ping')
                ->andReturn($healthy);
        }

        Redis::shouldReceive('ping')->andReturn($healthy);
        DB::shouldReceive('select')->with('SELECT 1')->andReturn($healthy ? [['1' => 1]] : []);
    }

    public function test_exits_successfully_when_all_services_are_healthy(): void
    {
        $this->mockAllExternalServices(true);

        $this->artisan('princess:health')
            ->assertExitCode(0);
    }

    public function test_exits_with_failure_when_a_service_is_unreachable(): void
    {
        $this->mockAllExternalServices(true);

        $this->mock(AuthGatewayClientContract::class)
            ->shouldReceive('ping')
            ->andReturn(false);

        $this->artisan('princess:health')
            ->assertExitCode(1);
    }

    public function test_output_lists_all_service_names(): void
    {
        $this->mockAllExternalServices(true);

        $this->artisan('princess:health')
            ->expectsOutputToContain('Database')
            ->expectsOutputToContain('Redis')
            ->expectsOutputToContain('Auth Backend')
            ->expectsOutputToContain('ZincSearch')
            ->expectsOutputToContain('Qdrant')
            ->expectsOutputToContain('Ollama')
            ->expectsOutputToContain('M365 Graph');
    }

    public function test_output_reports_failure_summary_when_services_are_down(): void
    {
        $this->mockAllExternalServices(false);

        Redis::shouldReceive('ping')->andReturn(false);

        $this->artisan('princess:health')
            ->expectsOutputToContain('unreachable');
    }
}
