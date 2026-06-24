<?php

namespace Tests\Feature\Auth;

use App\Services\Auth\AuthService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    public function test_login_returns_200_with_redirect_uri(): void
    {
        $this->postJson('/api/auth/login')
            ->assertStatus(200)
            ->assertJsonStructure(['redirect_uri'])
            ->assertJsonPath('redirect_uri', fn ($url) => str_contains($url, '/login'));
    }

    public function test_validate_access_token_returns_401_without_authorization_header(): void
    {
        $this->getJson('/api/auth/validate-access-token')
            ->assertStatus(401);
    }

    public function test_logout_returns_401_without_authorization_header(): void
    {
        $this->postJson('/api/auth/logout')
            ->assertStatus(401);
    }

    public function test_validate_returns_true_when_auth_backend_confirms_valid(): void
    {
        Http::fake(['*/api/auth/validate-access-token' => Http::response(true, 200)]);

        $this->withHeaders([
            'Authorization'      => 'Bearer valid-token',
            'X-Refresh-Token'    => 'refresh-token',
            'X-Application-Name' => 'princess_backend',
            'X-Client-Url'       => 'http://localhost:10100',
        ])->getJson('/api/auth/validate-access-token')
            ->assertStatus(200);
    }

    public function test_logout_returns_503_when_auth_backend_is_unavailable(): void
    {
        Http::fake(['*/api/auth/logout' => fn () => throw new \Illuminate\Http\Client\ConnectionException('refused')]);

        $this->withHeaders([
            'Authorization'      => 'Bearer valid-token',
            'X-Refresh-Token'    => 'refresh-token',
            'X-Application-Name' => 'princess_backend',
            'X-Client-Url'       => 'http://localhost:10100',
        ])->postJson('/api/auth/logout')
            ->assertStatus(503);
    }

    public function test_validate_access_token_returns_true_for_e2e_authenticated_request(): void
    {
        $this->withHeader('X-E2E-Token', config('app.e2e_token'))
            ->getJson('/api/auth/validate-access-token')
            ->assertOk()
            ->assertContent('"true"');
    }
}
