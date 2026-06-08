<?php

namespace Tests\Feature\Auth;

use App\Services\Auth\AuthService;
use App\Services\User\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VerifyFrontendMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['project_manager', 'project_board', 'quality_assurance', 'team_manager', 'observer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        Route::middleware('verify.frontend')
            ->get('/_test/protected', fn () => response()->json(['ok' => true]));
    }

    private function validHeaders(): array
    {
        return [
            'Authorization'      => 'Bearer valid-token',
            'X-Refresh-Token'    => 'refresh-token',
            'X-Application-Name' => 'princess_backend',
            'X-Client-Url'       => 'http://localhost:10100',
        ];
    }

    private function validTokenPayload(): string
    {
        $claims = [
            'sub'                => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'email'              => 'user@example.com',
            'preferred_username' => 'testuser',
            'name'               => 'Test User',
            'realm_access'       => ['roles' => []],
        ];
        $payload = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');
        return "fakeheader.{$payload}.fakesig";
    }

    public function test_returns_401_when_bearer_token_absent(): void
    {
        $this->getJson('/_test/protected')->assertStatus(401);
    }

    public function test_returns_401_when_refresh_token_header_absent(): void
    {
        $this->withHeaders([
            'Authorization'      => 'Bearer tok',
            'X-Application-Name' => 'princess_backend',
            'X-Client-Url'       => 'http://localhost:10100',
        ])->getJson('/_test/protected')->assertStatus(401);
    }

    public function test_returns_401_when_application_name_header_absent(): void
    {
        $this->withHeaders([
            'Authorization'   => 'Bearer tok',
            'X-Refresh-Token' => 'ref',
            'X-Client-Url'    => 'http://localhost:10100',
        ])->getJson('/_test/protected')->assertStatus(401);
    }

    public function test_returns_401_when_client_url_header_absent(): void
    {
        $this->withHeaders([
            'Authorization'      => 'Bearer tok',
            'X-Refresh-Token'    => 'ref',
            'X-Application-Name' => 'princess_backend',
        ])->getJson('/_test/protected')->assertStatus(401);
    }

    public function test_returns_401_when_auth_service_returns_failure(): void
    {
        Http::fake(['*/api/auth/validate-access-token' => Http::response(false, 401)]);

        $this->withHeaders($this->validHeaders())
            ->getJson('/_test/protected')
            ->assertStatus(401);
    }

    public function test_returns_200_and_logs_in_user_when_token_is_valid(): void
    {
        $token = $this->validTokenPayload();

        Http::fake(['*/api/auth/validate-access-token' => Http::response(true, 200)]);

        $this->withHeaders(array_merge($this->validHeaders(), ['Authorization' => "Bearer {$token}"]))
            ->getJson('/_test/protected')
            ->assertStatus(200);
    }

    public function test_returns_503_when_auth_service_throws_connection_exception(): void
    {
        Http::fake(['*/api/auth/validate-access-token' => fn () => throw new \Illuminate\Http\Client\ConnectionException('refused')]);

        $this->withHeaders($this->validHeaders())
            ->getJson('/_test/protected')
            ->assertStatus(503);
    }
}
