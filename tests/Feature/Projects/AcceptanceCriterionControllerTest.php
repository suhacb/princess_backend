<?php

namespace Tests\Feature\Projects;

use App\Enums\AcceptanceCriterionStatus;
use App\Enums\ProjectRole;
use App\Enums\RequirementType;
use App\Models\AcceptanceCriterion;
use App\Models\Person;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcceptanceCriterionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Person $person;
    private Project $project;
    private Requirement $requirement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\VerifyFrontend::class);

        $this->person = Person::factory()->create();
        $this->user   = User::factory()->create(['person_id' => $this->person->id]);
        $this->actingAs($this->user);

        $this->project     = Project::factory()->create(['created_by' => $this->person->id]);
        $this->project->members()->create([
            'person_id' => $this->person->id,
            'role'      => ProjectRole::ProjectManager->value,
        ]);

        $this->requirement = Requirement::factory()->create([
            'project_id' => $this->project->id,
            'type'       => RequirementType::Classic->value,
            'created_by' => $this->person->id,
        ]);
    }

    private function indexUrl(): string
    {
        return "/api/projects/{$this->project->id}/acceptance-criteria";
    }

    private function criterionUrl(AcceptanceCriterion $ac): string
    {
        return "/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}";
    }

    private function makeCriterion(array $attributes = []): AcceptanceCriterion
    {
        return AcceptanceCriterion::factory()->create(array_merge([
            'project_id'     => $this->project->id,
            'requirement_id' => $this->requirement->id,
            'created_by'     => $this->person->id,
        ], $attributes));
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'requirement_id' => $this->requirement->id,
            'description'    => 'The system shall respond within 200ms',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_lists_acceptance_criteria(): void
    {
        $this->makeCriterion();
        $this->makeCriterion();

        $this->getJson($this->indexUrl())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_requirement(): void
    {
        $other = Requirement::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
            'ref'        => 'REQ-002',
        ]);

        $this->makeCriterion();
        AcceptanceCriterion::factory()->create([
            'project_id'     => $this->project->id,
            'requirement_id' => $other->id,
            'created_by'     => $this->person->id,
            'ref'            => 'AC-002',
        ]);

        $this->getJson($this->indexUrl() . "?requirement_id={$this->requirement->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_status(): void
    {
        $this->makeCriterion(['status' => AcceptanceCriterionStatus::Draft->value]);
        $this->makeCriterion(['status' => AcceptanceCriterionStatus::Approved->value, 'ref' => 'AC-002']);

        $this->getJson($this->indexUrl() . '?status=approved')
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
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_acceptance_criterion(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload())
            ->assertCreated()
            ->assertJsonPath('data.ref', 'AC-001')
            ->assertJsonPath('data.status', AcceptanceCriterionStatus::Draft->value)
            ->assertJsonPath('data.description', 'The system shall respond within 200ms');

        $this->assertDatabaseHas('acceptance_criteria', [
            'project_id'     => $this->project->id,
            'requirement_id' => $this->requirement->id,
            'ref'            => 'AC-001',
            'created_by'     => $this->person->id,
        ]);
    }

    public function test_store_ref_increments_per_project(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload())->assertCreated()->assertJsonPath('data.ref', 'AC-001');
        $this->postJson($this->indexUrl(), $this->storePayload(['description' => 'Second']))->assertCreated()->assertJsonPath('data.ref', 'AC-002');
    }

    public function test_store_forbidden_for_read_only_role(): void
    {
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($observer)
            ->postJson($this->indexUrl(), $this->storePayload())
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // store – validation
    // -------------------------------------------------------------------------

    public function test_store_requires_requirement_id(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['requirement_id' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('requirement_id');
    }

    public function test_store_requires_description(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['description' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('description');
    }

    public function test_store_rejects_requirement_from_another_project(): void
    {
        $other      = Project::factory()->create(['created_by' => $this->person->id]);
        $foreignReq = Requirement::factory()->create(['project_id' => $other->id, 'created_by' => $this->person->id]);

        $this->postJson($this->indexUrl(), $this->storePayload(['requirement_id' => $foreignReq->id]))
            ->assertUnprocessable();
    }

    public function test_store_rejects_epic_as_requirement(): void
    {
        $epic = Requirement::factory()->epic()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
            'ref'        => 'REQ-002',
        ]);

        $this->postJson($this->indexUrl(), $this->storePayload(['requirement_id' => $epic->id]))
            ->assertUnprocessable();
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_criterion(): void
    {
        $ac = $this->makeCriterion();

        $this->getJson($this->criterionUrl($ac))
            ->assertOk()
            ->assertJsonPath('data.id', $ac->id);
    }

    public function test_show_forbidden_for_non_member(): void
    {
        $ac       = $this->makeCriterion();
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->criterionUrl($ac))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_edits_criterion(): void
    {
        $ac = $this->makeCriterion();

        $this->putJson($this->criterionUrl($ac), ['description' => 'Updated criterion'])
            ->assertOk()
            ->assertJsonPath('data.description', 'Updated criterion');
    }

    public function test_update_forbidden_for_read_only_role(): void
    {
        $ac             = $this->makeCriterion();
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($observer)
            ->putJson($this->criterionUrl($ac), ['description' => 'Hijacked'])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_draft_criterion(): void
    {
        $ac = $this->makeCriterion(['status' => AcceptanceCriterionStatus::Draft->value]);

        $this->deleteJson($this->criterionUrl($ac))->assertNoContent();
        $this->assertSoftDeleted('acceptance_criteria', ['id' => $ac->id]);
    }

    public function test_destroy_forbidden_on_approved_criterion(): void
    {
        $ac = $this->makeCriterion(['status' => AcceptanceCriterionStatus::Approved->value]);

        $this->deleteJson($this->criterionUrl($ac))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // approve
    // -------------------------------------------------------------------------

    public function test_approve_transitions_draft_to_approved(): void
    {
        $ac              = $this->makeCriterion(['status' => AcceptanceCriterionStatus::Draft->value]);
        $assurancePerson = Person::factory()->create();
        $assurance       = User::factory()->create(['person_id' => $assurancePerson->id]);
        $this->project->members()->create(['person_id' => $assurancePerson->id, 'role' => ProjectRole::ProjectAssurance->value]);

        $this->actingAs($assurance)
            ->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', AcceptanceCriterionStatus::Approved->value);

        $this->assertDatabaseHas('acceptance_criteria', [
            'id'          => $ac->id,
            'status'      => AcceptanceCriterionStatus::Approved->value,
            'approved_by' => $assurancePerson->id,
        ]);
    }

    public function test_approve_allowed_for_board_roles(): void
    {
        foreach ([ProjectRole::Executive, ProjectRole::SeniorUser, ProjectRole::SeniorSupplier] as $role) {
            $boardPerson = Person::factory()->create();
            $boardUser   = User::factory()->create(['person_id' => $boardPerson->id]);
            $this->project->members()->create(['person_id' => $boardPerson->id, 'role' => $role->value]);

            $ac = $this->makeCriterion([
                'status' => AcceptanceCriterionStatus::Draft->value,
                'ref'    => 'AC-' . fake()->unique()->numerify('###'),
            ]);

            $this->actingAs($boardUser)
                ->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/approve")
                ->assertOk("Role {$role->value} should be able to approve");
        }
    }

    public function test_approve_forbidden_for_project_manager(): void
    {
        $ac = $this->makeCriterion(['status' => AcceptanceCriterionStatus::Draft->value]);

        $this->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/approve")
            ->assertForbidden();
    }

    public function test_approve_returns_409_if_already_approved(): void
    {
        $ac              = $this->makeCriterion(['status' => AcceptanceCriterionStatus::Approved->value]);
        $assurancePerson = Person::factory()->create();
        $assurance       = User::factory()->create(['person_id' => $assurancePerson->id]);
        $this->project->members()->create(['person_id' => $assurancePerson->id, 'role' => ProjectRole::ProjectAssurance->value]);

        $this->actingAs($assurance)
            ->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/approve")
            ->assertStatus(409);
    }
}
