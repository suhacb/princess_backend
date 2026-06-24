<?php

namespace Tests\Feature\E2e;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class E2eAuthMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = config('app.e2e_token');

        Route::get('/_test/e2e-probe', function () {
            return response()->json([
                'authenticated' => Auth::check(),
                'e2e_flag'      => request()->attributes->get('e2e_authenticated', false),
                'connection'    => DB::getDefaultConnection(),
            ]);
        });
    }

    protected function tearDown(): void
    {
        DB::connection('e2e')->table('users')->where('email', 'e2e@princess.test')->delete();
        parent::tearDown();
    }

    public function test_request_without_header_passes_through_normally(): void
    {
        $this->getJson('/_test/e2e-probe')
            ->assertOk()
            ->assertJsonPath('e2e_flag', false);
    }

    public function test_wrong_token_returns_401(): void
    {
        $this->withHeader('X-E2E-Token', 'wrong-token')
            ->getJson('/_test/e2e-probe')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'Invalid E2E token');
    }

    public function test_correct_token_sets_e2e_flag_and_logs_in_user(): void
    {
        $response = $this->withHeader('X-E2E-Token', $this->token)
            ->getJson('/_test/e2e-probe')
            ->assertOk();

        $response->assertJsonPath('e2e_flag', true);
        $response->assertJsonPath('authenticated', true);
    }

    public function test_correct_token_switches_default_connection_to_e2e(): void
    {
        $this->withHeader('X-E2E-Token', $this->token)
            ->getJson('/_test/e2e-probe')
            ->assertOk()
            ->assertJsonPath('connection', 'e2e');
    }

    public function test_e2e_user_is_created_if_not_present(): void
    {
        $e2e = DB::connection('e2e');
        $this->assertSame(0, $e2e->table('users')->where('email', 'e2e@princess.test')->count());

        $this->withHeader('X-E2E-Token', $this->token)
            ->getJson('/_test/e2e-probe')
            ->assertOk();

        $this->assertSame(1, $e2e->table('users')->where('email', 'e2e@princess.test')->count());
    }

    public function test_e2e_user_is_reused_if_already_exists(): void
    {
        $e2e = DB::connection('e2e');
        $e2e->table('users')->insert([
            'email'       => 'e2e@princess.test',
            'name'        => 'E2E User',
            'external_id' => 'e2e-user',
            'username'    => 'e2e',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->withHeader('X-E2E-Token', $this->token)
            ->getJson('/_test/e2e-probe')
            ->assertOk();

        $this->assertSame(1, $e2e->table('users')->where('email', 'e2e@princess.test')->count());
    }

    public function test_no_bypass_when_e2e_token_not_configured(): void
    {
        config(['app.e2e_token' => null]);

        $this->withHeader('X-E2E-Token', 'any-value')
            ->getJson('/_test/e2e-probe')
            ->assertOk()
            ->assertJsonPath('e2e_flag', false);
    }
}
