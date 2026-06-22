<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Enums\RequirementType;
use App\Models\Person;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Person $person;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\VerifyFrontend::class);

        $this->person = Person::factory()->create();
        $this->user   = User::factory()->create(['person_id' => $this->person->id]);
        $this->actingAs($this->user);

        $this->project = Project::factory()->create(['created_by' => $this->person->id]);
        $this->project->members()->create([
            'person_id' => $this->person->id,
            'role'      => ProjectRole::ProjectManager->value,
        ]);
    }

    private function indexUrl(): string
    {
        return "/api/projects/{$this->project->id}/requirements";
    }

    private function requirementUrl(Requirement $req): string
    {
        return "/api/projects/{$this->project->id}/requirements/{$req->id}";
    }

    private function classicPayload(array $overrides = []): array
    {
        return array_merge([
            'type'     => RequirementType::Classic->value,
            'title'    => 'System shall log all user actions',
            'priority' => RequirementPriority::Must->value,
        ], $overrides);
    }

    private function userStoryPayload(array $overrides = []): array
    {
        return array_merge([
            'type'     => RequirementType::UserStory->value,
            'title'    => 'Login flow',
            'priority' => RequirementPriority::Must->value,
            'role'     => 'project manager',
            'action'   => 'log in with my credentials',
            'benefit'  => 'I can access the system securely',
        ], $overrides);
    }

    private function makeRequirement(array $attributes = []): Requirement
    {
        return Requirement::factory()->create(array_merge([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ], $attributes));
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_lists_requirements(): void
    {
        $this->makeRequirement();
        $this->makeRequirement();

        $this->getJson($this->indexUrl())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_type(): void
    {
        $this->makeRequirement(['type' => RequirementType::Classic->value]);
        $this->makeRequirement(['type' => RequirementType::Epic->value, 'ref' => 'REQ-002']);

        $this->getJson($this->indexUrl() . '?type=epic')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', RequirementType::Epic->value);
    }

    public function test_index_filters_by_status(): void
    {
        $this->makeRequirement(['status' => RequirementStatus::Draft->value]);
        $this->makeRequirement(['status' => RequirementStatus::Approved->value, 'ref' => 'REQ-002']);

        $this->getJson($this->indexUrl() . '?status=approved')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_priority(): void
    {
        $this->makeRequirement(['priority' => RequirementPriority::Must->value]);
        $this->makeRequirement(['priority' => RequirementPriority::Could->value, 'ref' => 'REQ-002']);

        $this->getJson($this->indexUrl() . '?priority=could')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_forbidden_for_non_member(): void
    {
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->indexUrl())
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // store – classic
    // -------------------------------------------------------------------------

    public function test_store_creates_classic_requirement(): void
    {
        $this->postJson($this->indexUrl(), $this->classicPayload())
            ->assertCreated()
            ->assertJsonPath('data.type', RequirementType::Classic->value)
            ->assertJsonPath('data.status', RequirementStatus::Draft->value)
            ->assertJsonPath('data.version', 1);

        $this->assertDatabaseHas('requirements', [
            'project_id' => $this->project->id,
            'type'       => RequirementType::Classic->value,
            'ref'        => 'REQ-001',
            'created_by' => $this->person->id,
        ]);
    }

    public function test_store_creates_epic(): void
    {
        $this->postJson($this->indexUrl(), [
            'type'     => RequirementType::Epic->value,
            'title'    => 'Authentication epic',
            'priority' => RequirementPriority::Must->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', RequirementType::Epic->value)
            ->assertJsonPath('data.ref', 'REQ-001');
    }

    public function test_store_creates_user_story(): void
    {
        $this->postJson($this->indexUrl(), $this->userStoryPayload())
            ->assertCreated()
            ->assertJsonPath('data.type', RequirementType::UserStory->value)
            ->assertJsonPath('data.ref', 'US-001')
            ->assertJsonPath('data.role', 'project manager');
    }

    public function test_store_user_story_under_epic(): void
    {
        $epic = $this->makeRequirement(['type' => RequirementType::Epic->value]);

        $this->postJson($this->indexUrl(), $this->userStoryPayload(['parent_id' => $epic->id]))
            ->assertCreated()
            ->assertJsonPath('data.parent_id', $epic->id);
    }

    public function test_store_ref_sequences_are_independent(): void
    {
        // REQ sequence: classic + epic share it
        $this->postJson($this->indexUrl(), $this->classicPayload())->assertCreated()->assertJsonPath('data.ref', 'REQ-001');
        $this->postJson($this->indexUrl(), ['type' => RequirementType::Epic->value, 'title' => 'E', 'priority' => 'must'])
            ->assertCreated()->assertJsonPath('data.ref', 'REQ-002');

        // US sequence is separate
        $this->postJson($this->indexUrl(), $this->userStoryPayload())->assertCreated()->assertJsonPath('data.ref', 'US-001');
        $this->postJson($this->indexUrl(), $this->userStoryPayload(['title' => 'Second']))->assertCreated()->assertJsonPath('data.ref', 'US-002');
    }

    public function test_store_forbidden_for_read_only_role(): void
    {
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($observer)
            ->postJson($this->indexUrl(), $this->classicPayload())
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // store – validation
    // -------------------------------------------------------------------------

    public function test_store_requires_type(): void
    {
        $this->postJson($this->indexUrl(), $this->classicPayload(['type' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_store_rejects_invalid_type(): void
    {
        $this->postJson($this->indexUrl(), $this->classicPayload(['type' => 'bogus']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_store_requires_title(): void
    {
        $this->postJson($this->indexUrl(), $this->classicPayload(['title' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_store_requires_priority(): void
    {
        $this->postJson($this->indexUrl(), $this->classicPayload(['priority' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('priority');
    }

    public function test_store_user_story_requires_role_action_benefit(): void
    {
        $this->postJson($this->indexUrl(), [
            'type'     => RequirementType::UserStory->value,
            'title'    => 'Story without structure',
            'priority' => RequirementPriority::Must->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role', 'action', 'benefit']);
    }

    public function test_store_classic_rejects_user_story_fields(): void
    {
        $this->postJson($this->indexUrl(), $this->classicPayload(['role' => 'someone']))
            ->assertUnprocessable();
    }

    public function test_store_epic_cannot_have_parent(): void
    {
        $other = $this->makeRequirement(['type' => RequirementType::Epic->value]);

        $this->postJson($this->indexUrl(), [
            'type'      => RequirementType::Epic->value,
            'title'     => 'Sub-epic',
            'priority'  => RequirementPriority::Must->value,
            'parent_id' => $other->id,
        ])->assertUnprocessable();
    }

    public function test_store_user_story_parent_must_be_epic_in_same_project(): void
    {
        $classic = $this->makeRequirement(['type' => RequirementType::Classic->value]);

        $this->postJson($this->indexUrl(), $this->userStoryPayload(['parent_id' => $classic->id]))
            ->assertUnprocessable();
    }

    public function test_store_rejects_parent_from_another_project(): void
    {
        $other    = Project::factory()->create(['created_by' => $this->person->id]);
        $foreignEpic = Requirement::factory()->epic()->create(['project_id' => $other->id, 'created_by' => $this->person->id]);

        $this->postJson($this->indexUrl(), $this->userStoryPayload(['parent_id' => $foreignEpic->id]))
            ->assertUnprocessable();
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_requirement(): void
    {
        $req = $this->makeRequirement();

        $this->getJson($this->requirementUrl($req))
            ->assertOk()
            ->assertJsonPath('data.id', $req->id);
    }

    public function test_show_forbidden_for_non_member(): void
    {
        $req      = $this->makeRequirement();
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->requirementUrl($req))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_edits_requirement_and_increments_version(): void
    {
        $req = $this->makeRequirement(['version' => 1]);

        $this->putJson($this->requirementUrl($req), ['title' => 'Updated title'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title')
            ->assertJsonPath('data.version', 2);
    }

    public function test_update_forbidden_for_read_only_role(): void
    {
        $req            = $this->makeRequirement();
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($observer)
            ->putJson($this->requirementUrl($req), ['title' => 'Hijacked'])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_draft_requirement(): void
    {
        $req = $this->makeRequirement(['status' => RequirementStatus::Draft->value]);

        $this->deleteJson($this->requirementUrl($req))->assertNoContent();
        $this->assertSoftDeleted('requirements', ['id' => $req->id]);
    }

    public function test_destroy_forbidden_on_reviewed_requirement(): void
    {
        $req = $this->makeRequirement(['status' => RequirementStatus::Reviewed->value]);

        $this->deleteJson($this->requirementUrl($req))->assertForbidden();
    }

    public function test_destroy_forbidden_on_epic_with_children(): void
    {
        $epic = $this->makeRequirement(['type' => RequirementType::Epic->value]);
        Requirement::factory()->userStory()->create([
            'project_id' => $this->project->id,
            'parent_id'  => $epic->id,
            'created_by' => $this->person->id,
        ]);

        $this->deleteJson($this->requirementUrl($epic))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // status transitions
    // -------------------------------------------------------------------------

    public function test_review_transitions_draft_to_reviewed(): void
    {
        $req = $this->makeRequirement(['status' => RequirementStatus::Draft->value]);

        $this->postJson("/api/projects/{$this->project->id}/requirements/{$req->id}/review")
            ->assertOk()
            ->assertJsonPath('data.status', RequirementStatus::Reviewed->value);
    }

    public function test_review_returns_409_if_not_draft(): void
    {
        $req = $this->makeRequirement(['status' => RequirementStatus::Reviewed->value]);

        $this->postJson("/api/projects/{$this->project->id}/requirements/{$req->id}/review")
            ->assertStatus(409);
    }

    public function test_approve_transitions_reviewed_to_approved(): void
    {
        $req = $this->makeRequirement(['status' => RequirementStatus::Reviewed->value]);

        $assurancePerson = Person::factory()->create();
        $assurance       = User::factory()->create(['person_id' => $assurancePerson->id]);
        $this->project->members()->create(['person_id' => $assurancePerson->id, 'role' => ProjectRole::ProjectAssurance->value]);

        $this->actingAs($assurance)
            ->postJson("/api/projects/{$this->project->id}/requirements/{$req->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', RequirementStatus::Approved->value);

        $this->assertDatabaseHas('requirements', [
            'id'          => $req->id,
            'status'      => RequirementStatus::Approved->value,
            'approved_by' => $assurancePerson->id,
        ]);
    }

    public function test_approve_allowed_for_board_roles(): void
    {
        foreach ([ProjectRole::Executive, ProjectRole::SeniorUser, ProjectRole::SeniorSupplier] as $role) {
            $boardPerson = Person::factory()->create();
            $boardUser   = User::factory()->create(['person_id' => $boardPerson->id]);
            $this->project->members()->create(['person_id' => $boardPerson->id, 'role' => $role->value]);

            $req = $this->makeRequirement(['status' => RequirementStatus::Reviewed->value, 'ref' => 'REQ-' . fake()->unique()->numerify('###')]);

            $this->actingAs($boardUser)
                ->postJson("/api/projects/{$this->project->id}/requirements/{$req->id}/approve")
                ->assertOk("Role {$role->value} should be able to approve");
        }
    }

    public function test_approve_forbidden_for_project_manager(): void
    {
        $req = $this->makeRequirement(['status' => RequirementStatus::Reviewed->value]);

        $this->postJson("/api/projects/{$this->project->id}/requirements/{$req->id}/approve")
            ->assertForbidden();
    }

    public function test_approve_returns_409_if_not_reviewed(): void
    {
        $assurancePerson = Person::factory()->create();
        $assurance       = User::factory()->create(['person_id' => $assurancePerson->id]);
        $this->project->members()->create(['person_id' => $assurancePerson->id, 'role' => ProjectRole::ProjectAssurance->value]);

        $req = $this->makeRequirement(['status' => RequirementStatus::Draft->value]);

        $this->actingAs($assurance)
            ->postJson("/api/projects/{$this->project->id}/requirements/{$req->id}/approve")
            ->assertStatus(409);
    }

    public function test_reject_transitions_reviewed_to_rejected(): void
    {
        $req             = $this->makeRequirement(['status' => RequirementStatus::Reviewed->value]);
        $assurancePerson = Person::factory()->create();
        $assurance       = User::factory()->create(['person_id' => $assurancePerson->id]);
        $this->project->members()->create(['person_id' => $assurancePerson->id, 'role' => ProjectRole::ProjectAssurance->value]);

        $this->actingAs($assurance)
            ->postJson("/api/projects/{$this->project->id}/requirements/{$req->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', RequirementStatus::Rejected->value);
    }

    public function test_reject_forbidden_for_project_manager(): void
    {
        $req = $this->makeRequirement(['status' => RequirementStatus::Reviewed->value]);

        $this->postJson("/api/projects/{$this->project->id}/requirements/{$req->id}/reject")
            ->assertForbidden();
    }

    public function test_defer_transitions_any_status_to_deferred(): void
    {
        foreach ([RequirementStatus::Draft, RequirementStatus::Reviewed, RequirementStatus::Approved] as $status) {
            $req = $this->makeRequirement(['status' => $status->value, 'ref' => 'REQ-' . fake()->unique()->numerify('###')]);

            $this->postJson("/api/projects/{$this->project->id}/requirements/{$req->id}/defer")
                ->assertOk()
                ->assertJsonPath('data.status', RequirementStatus::Deferred->value);
        }
    }

    public function test_defer_forbidden_for_non_pm(): void
    {
        $req             = $this->makeRequirement();
        $assurancePerson = Person::factory()->create();
        $assurance       = User::factory()->create(['person_id' => $assurancePerson->id]);
        $this->project->members()->create(['person_id' => $assurancePerson->id, 'role' => ProjectRole::ProjectAssurance->value]);

        $this->actingAs($assurance)
            ->postJson("/api/projects/{$this->project->id}/requirements/{$req->id}/defer")
            ->assertForbidden();
    }
}
