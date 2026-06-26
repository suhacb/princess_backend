<?php

namespace Tests\Feature\E2e;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class E2eControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = config('app.e2e_token');
    }

    protected function tearDown(): void
    {
        DB::connection('e2e')->table('users')->where('external_id', 'like', 'e2e-%')->delete();
        parent::tearDown();
    }

    public function test_reset_without_token_returns_401(): void
    {
        $this->postJson('/api/e2e/reset')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'E2E token required');
    }

    public function test_reset_with_wrong_token_returns_401(): void
    {
        $this->withHeader('X-E2E-Token', 'wrong')
            ->postJson('/api/e2e/reset')
            ->assertUnauthorized();
    }

    public function test_reset_full_calls_migrate_fresh_with_e2e_connection(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate:fresh', [
                '--database' => 'e2e',
                '--seed'     => true,
                '--seeder'   => 'E2eSeeder',
                '--force'    => true,
            ]);

        $this->withHeader('X-E2E-Token', $this->token)
            ->postJson('/api/e2e/reset?full=true')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_reset_fast_path_returns_ok_and_seeds(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('db:seed', [
                '--class'    => 'E2eSeeder',
                '--database' => 'e2e',
                '--force'    => true,
            ]);

        $this->withHeader('X-E2E-Token', $this->token)
            ->postJson('/api/e2e/reset')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_full_true_does_not_call_db_seed(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate:fresh', \Mockery::any());

        Artisan::shouldReceive('call')
            ->with('db:seed', \Mockery::any())
            ->never();

        $this->withHeader('X-E2E-Token', $this->token)
            ->postJson('/api/e2e/reset?full=true')
            ->assertOk();
    }
}
