<?php

namespace Tests\Unit\Auth;

use App\Classes\Auth\TokenParser;
use App\Enums\PersonSide;
use App\Services\User\UserService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

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
            'groups'             => [],
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
        $this->service->handleUserFromToken($this->buildToken($this->validClaims([
            'realm_access' => ['roles' => ['project_manager', 'observer']],
        ])));

        $user = $this->service->handleUserFromToken($this->buildToken($this->validClaims([
            'realm_access' => ['roles' => ['project_manager']],
        ])));

        $this->assertTrue($user->hasRole('project_manager'));
        $this->assertFalse($user->hasRole('observer'));
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

    public function test_creates_and_links_person_on_first_login(): void
    {
        $claims = $this->validClaims();

        $user = $this->service->handleUserFromToken($this->buildToken($claims));

        $this->assertNotNull($user->person_id);
        $this->assertDatabaseHas('people', ['email' => $claims['email'], 'name' => $claims['name']]);
    }

    public function test_reuses_existing_person_on_subsequent_login(): void
    {
        $token = $this->buildToken($this->validClaims());

        $this->service->handleUserFromToken($token);
        $this->service->handleUserFromToken($token);

        $this->assertDatabaseCount('people', 1);
    }

    public function test_does_not_overwrite_person_link_if_already_set(): void
    {
        $token = $this->buildToken($this->validClaims());
        $user  = $this->service->handleUserFromToken($token);

        $originalPersonId = $user->person_id;
        $this->service->handleUserFromToken($token);

        $this->assertEquals($originalPersonId, $user->fresh()->person_id);
    }

    public function test_syncs_side_customer_from_keycloak_group(): void
    {
        $claims = $this->validClaims(['groups' => ['/customer']]);

        $user = $this->service->handleUserFromToken($this->buildToken($claims));

        $this->assertEquals(PersonSide::Customer, $user->person->fresh()->side);
    }

    public function test_syncs_side_supplier_from_keycloak_group(): void
    {
        $claims = $this->validClaims(['groups' => ['/supplier']]);

        $user = $this->service->handleUserFromToken($this->buildToken($claims));

        $this->assertEquals(PersonSide::Supplier, $user->person->fresh()->side);
    }

    public function test_does_not_set_side_when_no_matching_group(): void
    {
        $claims = $this->validClaims(['groups' => ['/some-other-group']]);

        $user = $this->service->handleUserFromToken($this->buildToken($claims));

        $this->assertNull($user->person->fresh()->side);
    }
}
