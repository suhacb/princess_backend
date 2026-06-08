<?php

namespace Tests\Feature;

use App\Contracts\AuthGatewayClientContract;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_200_and_status_ok(): void
    {
        $this->getJson('/api/health')
            ->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }

    public function test_auth_backend_health_returns_200_when_client_is_healthy(): void
    {
        $this->mock(AuthGatewayClientContract::class)
            ->shouldReceive('ping')
            ->andReturn(true);

        $this->getJson('/api/health/auth')
            ->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }

    public function test_auth_backend_health_returns_503_when_client_is_unhealthy(): void
    {
        $this->mock(AuthGatewayClientContract::class)
            ->shouldReceive('ping')
            ->andReturn(false);

        $this->getJson('/api/health/auth')
            ->assertStatus(503)
            ->assertJson(['status' => 'unavailable']);
    }
}
