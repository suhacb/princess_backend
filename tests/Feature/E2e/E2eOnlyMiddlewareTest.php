<?php

namespace Tests\Feature\E2e;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class E2eOnlyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = config('app.e2e_token');

        Route::middleware('e2e.only')->post('/_test/e2e-only', fn () => response()->json(['ok' => true]));
    }

    public function test_request_without_e2e_token_returns_401(): void
    {
        $this->postJson('/_test/e2e-only')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'E2E token required');
    }

    public function test_request_with_wrong_token_returns_401_from_e2e_auth(): void
    {
        // E2eAuth rejects the invalid token before E2eOnly even runs
        $this->withHeader('X-E2E-Token', 'bad-token')
            ->postJson('/_test/e2e-only')
            ->assertUnauthorized();
    }

    public function test_request_with_valid_token_is_allowed_through(): void
    {
        $this->withHeader('X-E2E-Token', $this->token)
            ->postJson('/_test/e2e-only')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }
}
