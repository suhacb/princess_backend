<?php

namespace Tests\Feature\E2e;

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
        DB::connection('e2e')->table('users')->where('external_id', 'like', 'e2e-%')->delete();
        parent::tearDown();
    }

    private function makeJwt(string $sub): string
    {
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => $sub])), '+/', '-_'), '=');
        return "fakeheader.{$payload}.fakesig";
    }

    private function seedE2eUser(string $externalId): void
    {
        DB::connection('e2e')->table('users')->insert([
            'external_id' => $externalId,
            'username'    => str_replace('-', '_', $externalId),
            'name'        => 'E2E Test User',
            'email'       => "{$externalId}@princess.test",
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function test_request_without_e2e_header_passes_through_normally(): void
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

    public function test_correct_token_sets_e2e_flag(): void
    {
        $this->withHeader('X-E2E-Token', $this->token)
            ->getJson('/_test/e2e-probe')
            ->assertOk()
            ->assertJsonPath('e2e_flag', true);
    }

    public function test_correct_token_switches_default_connection_to_e2e(): void
    {
        $this->withHeader('X-E2E-Token', $this->token)
            ->getJson('/_test/e2e-probe')
            ->assertOk()
            ->assertJsonPath('connection', 'e2e');
    }

    public function test_logs_in_user_matching_jwt_sub(): void
    {
        $this->seedE2eUser('e2e-project-manager');

        $this->withHeaders([
                'X-E2E-Token'   => $this->token,
                'Authorization' => 'Bearer ' . $this->makeJwt('e2e-project-manager'),
            ])
            ->getJson('/_test/e2e-probe')
            ->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('e2e_flag', true);
    }

    public function test_no_bearer_token_sets_flag_but_skips_login(): void
    {
        $this->withHeader('X-E2E-Token', $this->token)
            ->getJson('/_test/e2e-probe')
            ->assertOk()
            ->assertJsonPath('e2e_flag', true)
            ->assertJsonPath('authenticated', false);
    }

    public function test_unknown_sub_in_jwt_sets_flag_but_skips_login(): void
    {
        $this->withHeaders([
                'X-E2E-Token'   => $this->token,
                'Authorization' => 'Bearer ' . $this->makeJwt('e2e-does-not-exist'),
            ])
            ->getJson('/_test/e2e-probe')
            ->assertOk()
            ->assertJsonPath('e2e_flag', true)
            ->assertJsonPath('authenticated', false);
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
