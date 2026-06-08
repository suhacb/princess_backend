<?php

namespace Tests\Unit\Auth;

use App\Classes\Auth\TokenParser;
use App\Services\User\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['project_manager', 'project_board', 'quality_assurance', 'team_manager', 'observer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->service = new UserService(new TokenParser());
    }

    private function buildToken(array $claims): string
    {
        $payload = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');
        return "fakeheader.{$payload}.fakesig";
    }

    private function validClaims(array $overrides = []): array
    {
        return array_merge([
            'sub'                => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'email'              => 'user@example.com',
            'preferred_username' => 'testuser',
            'name'               => 'Test User',
            'given_name'         => 'Test',
            'family_name'        => 'User',
            'realm_access'       => ['roles' => []],
        ], $overrides);
    }

    public function test_creates_new_user_from_token_claims(): void
    {
        $claims = $this->validClaims();

        $user = $this->service->handleUserFromToken($this->buildToken($claims));

        $this->assertDatabaseHas('users', ['external_id' => $claims['sub'], 'email' => $claims['email']]);
        $this->assertSame($claims['sub'], $user->external_id);
    }

    public function test_returns_existing_user_on_second_call_with_same_sub(): void
    {
        $token = $this->buildToken($this->validClaims());

        $this->service->handleUserFromToken($token);
        $this->service->handleUserFromToken($token);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_syncs_known_role_from_realm_access_claim(): void
    {
        $claims = $this->validClaims(['realm_access' => ['roles' => ['project_manager']]]);

        $user = $this->service->handleUserFromToken($this->buildToken($claims));

        $this->assertTrue($user->hasRole('project_manager'));
    }

    public function test_does_not_assign_unknown_roles(): void
    {
        $claims = $this->validClaims(['realm_access' => ['roles' => ['project_manager', 'some_keycloak_internal_role']]]);

        $user = $this->service->handleUserFromToken($this->buildToken($claims));

        $this->assertTrue($user->hasRole('project_manager'));
        $this->assertFalse($user->hasRole('some_keycloak_internal_role'));
    }

    public function test_syncs_roles_on_subsequent_login_removing_revoked_roles(): void
    {
        $sub = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

        $this->service->handleUserFromToken($this->buildToken($this->validClaims([
            'realm_access' => ['roles' => ['project_manager', 'project_board']],
        ])));

        $user = $this->service->handleUserFromToken($this->buildToken($this->validClaims([
            'realm_access' => ['roles' => ['project_manager']],
        ])));

        $this->assertTrue($user->hasRole('project_manager'));
        $this->assertFalse($user->hasRole('project_board'));
    }

    public function test_throws_when_sub_claim_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $claims = $this->validClaims();
        unset($claims['sub']);

        $this->service->handleUserFromToken($this->buildToken($claims));
    }

    public function test_throws_when_email_claim_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $claims = $this->validClaims();
        unset($claims['email']);

        $this->service->handleUserFromToken($this->buildToken($claims));
    }
}
