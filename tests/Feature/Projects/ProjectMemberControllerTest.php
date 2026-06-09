<?php

namespace Tests\Feature\Projects;

use App\Enums\PersonSide;
use App\Enums\ProjectRole;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectMemberControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Person $person;
    private Project $project;
    private ProjectMember $membership;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\VerifyFrontend::class);

        $this->person  = Person::factory()->create();
        $this->user    = User::factory()->create(['person_id' => $this->person->id]);
        $this->actingAs($this->user);

        $this->project    = Project::factory()->create(['created_by' => $this->person->id]);
        $this->membership = $this->project->members()->create([
            'person_id' => $this->person->id,
            'role'      => ProjectRole::ProjectManager->value,
        ]);
    }

    private function url(?ProjectMember $member = null): string
    {
        $base = "/api/projects/{$this->project->id}/members";
        return $member ? "{$base}/{$member->id}" : $base;
    }

    public function test_index_lists_project_members(): void
    {
        $other = Person::factory()->create();
        $this->project->members()->create([
            'person_id' => $other->id,
            'role'      => ProjectRole::TeamMember->value,
        ]);

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_forbidden_when_not_a_member(): void
    {
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->url())
            ->assertForbidden();
    }

    public function test_store_adds_a_new_member(): void
    {
        $newPerson = Person::factory()->create();

        $this->postJson($this->url(), [
            'person_id' => $newPerson->id,
            'role'      => ProjectRole::TeamMember->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.role', ProjectRole::TeamMember->value)
            ->assertJsonPath('data.person.id', $newPerson->id);

        $this->assertDatabaseHas('project_members', [
            'project_id' => $this->project->id,
            'person_id'  => $newPerson->id,
            'role'       => ProjectRole::TeamMember->value,
        ]);
    }

    public function test_store_sets_side(): void
    {
        $newPerson = Person::factory()->create();

        $this->postJson($this->url(), [
            'person_id' => $newPerson->id,
            'role'      => ProjectRole::SeniorUser->value,
            'side'      => PersonSide::Customer->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.side', PersonSide::Customer->value);
    }

    public function test_store_returns_422_for_invalid_role(): void
    {
        $newPerson = Person::factory()->create();

        $this->postJson($this->url(), [
            'person_id' => $newPerson->id,
            'role'      => 'dictator',
        ])->assertUnprocessable()->assertJsonValidationErrors(['role']);
    }

    public function test_store_returns_422_when_person_already_a_member(): void
    {
        $this->postJson($this->url(), [
            'person_id' => $this->person->id,
            'role'      => ProjectRole::Observer->value,
        ])->assertUnprocessable()->assertJsonValidationErrors(['person_id']);
    }

    public function test_store_forbidden_for_observer(): void
    {
        $observer       = Person::factory()->create();
        $observerUser   = User::factory()->create(['person_id' => $observer->id]);
        $this->project->members()->create([
            'person_id' => $observer->id,
            'role'      => ProjectRole::Observer->value,
        ]);

        $newPerson = Person::factory()->create();

        $this->actingAs($observerUser)
            ->postJson($this->url(), [
                'person_id' => $newPerson->id,
                'role'      => ProjectRole::TeamMember->value,
            ])
            ->assertForbidden();
    }

    public function test_update_changes_role(): void
    {
        $other       = Person::factory()->create();
        $otherMember = $this->project->members()->create([
            'person_id' => $other->id,
            'role'      => ProjectRole::TeamMember->value,
        ]);

        $this->putJson($this->url($otherMember), ['role' => ProjectRole::TeamManager->value])
            ->assertOk()
            ->assertJsonPath('data.role', ProjectRole::TeamManager->value);
    }

    public function test_update_changes_side(): void
    {
        $other       = Person::factory()->create();
        $otherMember = $this->project->members()->create([
            'person_id' => $other->id,
            'role'      => ProjectRole::SeniorSupplier->value,
        ]);

        $this->putJson($this->url($otherMember), ['side' => PersonSide::Supplier->value])
            ->assertOk()
            ->assertJsonPath('data.side', PersonSide::Supplier->value);
    }

    public function test_update_returns_422_when_removing_last_project_manager(): void
    {
        $this->putJson($this->url($this->membership), ['role' => ProjectRole::Observer->value])
            ->assertUnprocessable();
    }

    public function test_destroy_removes_member(): void
    {
        $other       = Person::factory()->create();
        $otherMember = $this->project->members()->create([
            'person_id' => $other->id,
            'role'      => ProjectRole::TeamMember->value,
        ]);

        $this->deleteJson($this->url($otherMember))->assertNoContent();

        $this->assertDatabaseMissing('project_members', ['id' => $otherMember->id]);
    }

    public function test_destroy_returns_422_when_removing_last_project_manager(): void
    {
        $this->deleteJson($this->url($this->membership))->assertUnprocessable();
    }

    public function test_destroy_allows_removing_project_manager_when_another_exists(): void
    {
        $other       = Person::factory()->create();
        $otherMember = $this->project->members()->create([
            'person_id' => $other->id,
            'role'      => ProjectRole::ProjectManager->value,
        ]);

        $this->deleteJson($this->url($this->membership))->assertNoContent();
    }
}
