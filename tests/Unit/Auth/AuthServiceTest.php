<?php

namespace Tests\Unit\Auth;

use App\Services\Auth\AuthService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuthService();
    }

    public function test_login_returns_redirect_url_with_app_name_and_url(): void
    {
        $url = $this->service->login();

        $this->assertStringContainsString('appName=', $url);
        $this->assertStringContainsString('appUrl=', $url);
        $this->assertStringContainsString('/login', $url);
    }

    public function test_validate_sends_get_request_with_correct_headers(): void
    {
        Http::fake(['*/api/auth/validate-access-token' => Http::response(true, 200)]);

        $this->service->validate('my-token', 'my-refresh', 'princess_backend', 'http://frontend');

        Http::assertSent(fn ($req) =>
            str_contains($req->url(), '/api/auth/validate-access-token')
            && $req->method() === 'GET'
            && $req->hasHeader('Authorization', 'Bearer my-token')
            && $req->hasHeader('X-Refresh-Token', 'my-refresh')
            && $req->hasHeader('X-Application-Name', 'princess_backend')
            && $req->hasHeader('X-Client-Url', 'http://frontend')
        );
    }

    public function test_validate_returns_response_from_auth_backend(): void
    {
        Http::fake(['*/api/auth/validate-access-token' => Http::response(true, 200)]);

        $response = $this->service->validate('tok', 'ref', 'app', 'url');

        $this->assertTrue($response->successful());
    }

    public function test_logout_sends_post_request_with_correct_headers(): void
    {
        Http::fake(['*/api/auth/logout' => Http::response([], 200)]);

        $this->service->logout('my-token', 'my-refresh', 'princess_backend', 'http://frontend');

        Http::assertSent(fn ($req) =>
            str_contains($req->url(), '/api/auth/logout')
            && $req->method() === 'POST'
            && $req->hasHeader('Authorization', 'Bearer my-token')
            && $req->hasHeader('X-Refresh-Token', 'my-refresh')
        );
    }
}
