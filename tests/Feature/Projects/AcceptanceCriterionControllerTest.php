<?php

namespace Tests\Feature\Projects;

use App\Enums\AcceptanceCriterionDecision;
use App\Enums\AcceptanceCriterionStatus;
use App\Enums\ProjectRole;
use App\Enums\RequirementType;
use App\Enums\VerificationMethod;
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
            'title'          => 'Response time criterion',
            'description'    => 'The system shall respond within 200ms',
        ], $overrides);
    }

    private function makeApprover(): User
    {
        $assurancePerson = Person::factory()->create();
        $assurance       = User::factory()->create(['person_id' => $assurancePerson->id]);
        $this->project->members()->create(['person_id' => $assurancePerson->id, 'role' => ProjectRole::ProjectAssurance->value]);

        return $assurance;
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
            ->assertJsonPath('data.title', 'Response time criterion')
            ->assertJsonPath('data.description', 'The system shall respond within 200ms')
            ->assertJsonPath('data.version', 1);

        $this->assertDatabaseHas('acceptance_criteria', [
            'project_id'     => $this->project->id,
            'requirement_id' => $this->requirement->id,
            'ref'            => 'AC-001',
            'title'          => 'Response time criterion',
            'created_by'     => $this->person->id,
        ]);
    }

    public function test_store_creates_initial_version_snapshot(): void
    {
        $response = $this->postJson($this->indexUrl(), $this->storePayload())->assertCreated();
        $ac       = AcceptanceCriterion::find($response->json('data.id'));

        $this->assertDatabaseHas('acceptance_criterion_versions', [
            'acceptance_criterion_id' => $ac->id,
            'version_number'          => 1,
            'title'                   => 'Response time criterion',
            'created_by'              => $this->person->id,
        ]);
    }

    public function test_store_accepts_verifier_and_verification_method(): void
    {
        $verifierPerson = Person::factory()->create();
        $this->project->members()->create(['person_id' => $verifierPerson->id, 'role' => ProjectRole::TeamMember->value]);

        $this->postJson($this->indexUrl(), $this->storePayload([
            'verifier_id'         => $verifierPerson->id,
            'verification_method' => VerificationMethod::Test->value,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.verification_method', VerificationMethod::Test->value);

        $this->assertDatabaseHas('acceptance_criteria', [
            'verifier_id'         => $verifierPerson->id,
            'verification_method' => VerificationMethod::Test->value,
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

    public function test_store_requires_title(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['title' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
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

    public function test_store_rejects_non_integer_requirement_id(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['requirement_id' => 'not-an-integer']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('requirement_id');
    }

    public function test_store_rejects_nonexistent_requirement_id(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['requirement_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('requirement_id');
    }

    public function test_store_rejects_title_exceeding_max_length(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['title' => str_repeat('a', 256)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_store_rejects_acceptance_threshold_exceeding_max_length(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['acceptance_threshold' => str_repeat('a', 256)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('acceptance_threshold');
    }

    public function test_store_rejects_nonexistent_verifier_id(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['verifier_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('verifier_id');
    }

    public function test_store_rejects_invalid_verification_method(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['verification_method' => 'not-a-method']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('verification_method');
    }

    public function test_store_accepts_measurement_method_and_acceptance_threshold(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload([
            'measurement_method'   => 'Automated load test',
            'acceptance_threshold' => 'Under 200ms for 95% of requests',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.measurement_method', 'Automated load test')
            ->assertJsonPath('data.acceptance_threshold', 'Under 200ms for 95% of requests');

        $this->assertDatabaseHas('acceptance_criteria', [
            'measurement_method'   => 'Automated load test',
            'acceptance_threshold' => 'Under 200ms for 95% of requests',
        ]);
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

    public function test_update_creates_new_version_snapshot(): void
    {
        $ac = $this->makeCriterion(['version' => 1, 'title' => 'Original title']);

        $this->putJson($this->criterionUrl($ac), ['title' => 'Updated title'])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->assertDatabaseHas('acceptance_criterion_versions', [
            'acceptance_criterion_id' => $ac->id,
            'version_number'          => 2,
            'title'                   => 'Updated title',
        ]);
    }

    public function test_update_with_no_actual_field_change_does_not_bump_version(): void
    {
        $ac = $this->makeCriterion(['version' => 1, 'description' => 'Same description']);

        $this->putJson($this->criterionUrl($ac), ['description' => 'Same description'])
            ->assertOk()
            ->assertJsonPath('data.version', 1);

        $this->assertDatabaseCount('acceptance_criterion_versions', 0);
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
    // update – validation
    // -------------------------------------------------------------------------

    public function test_update_rejects_title_set_to_null(): void
    {
        $ac = $this->makeCriterion();

        $this->putJson($this->criterionUrl($ac), ['title' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_update_rejects_description_set_to_null(): void
    {
        $ac = $this->makeCriterion();

        $this->putJson($this->criterionUrl($ac), ['description' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('description');
    }

    public function test_update_rejects_title_exceeding_max_length(): void
    {
        $ac = $this->makeCriterion();

        $this->putJson($this->criterionUrl($ac), ['title' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_update_rejects_acceptance_threshold_exceeding_max_length(): void
    {
        $ac = $this->makeCriterion();

        $this->putJson($this->criterionUrl($ac), ['acceptance_threshold' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('acceptance_threshold');
    }

    public function test_update_rejects_nonexistent_verifier_id(): void
    {
        $ac = $this->makeCriterion();

        $this->putJson($this->criterionUrl($ac), ['verifier_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('verifier_id');
    }

    public function test_update_rejects_invalid_verification_method(): void
    {
        $ac = $this->makeCriterion();

        $this->putJson($this->criterionUrl($ac), ['verification_method' => 'not-a-method'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('verification_method');
    }

    public function test_update_accepts_verifier_and_verification_method(): void
    {
        $ac             = $this->makeCriterion();
        $verifierPerson = Person::factory()->create();
        $this->project->members()->create(['person_id' => $verifierPerson->id, 'role' => ProjectRole::TeamMember->value]);

        $this->putJson($this->criterionUrl($ac), [
            'verifier_id'         => $verifierPerson->id,
            'verification_method' => VerificationMethod::Demo->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.verification_method', VerificationMethod::Demo->value);

        $this->assertDatabaseHas('acceptance_criteria', [
            'id'                  => $ac->id,
            'verifier_id'         => $verifierPerson->id,
            'verification_method' => VerificationMethod::Demo->value,
        ]);
    }

    public function test_update_accepts_acceptance_threshold(): void
    {
        $ac = $this->makeCriterion();

        $this->putJson($this->criterionUrl($ac), ['acceptance_threshold' => 'Under 300ms for 99% of requests'])
            ->assertOk()
            ->assertJsonPath('data.acceptance_threshold', 'Under 300ms for 99% of requests');

        $this->assertDatabaseHas('acceptance_criteria', [
            'id'                    => $ac->id,
            'acceptance_threshold'  => 'Under 300ms for 99% of requests',
        ]);
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

    public function test_approve_creates_version_snapshot(): void
    {
        $ac = $this->makeCriterion(['status' => AcceptanceCriterionStatus::Draft->value, 'version' => 1]);

        $this->actingAs($this->makeApprover())
            ->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->assertDatabaseHas('acceptance_criterion_versions', [
            'acceptance_criterion_id' => $ac->id,
            'version_number'          => 2,
            'status'                  => AcceptanceCriterionStatus::Approved->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // supplier-decision / client-decision
    // -------------------------------------------------------------------------

    public function test_supplier_decision_accepted_matching_computed_pass_does_not_require_note(): void
    {
        $ac = $this->makeCriterion(['supplier_passed' => true]);

        $this->actingAs($this->makeApprover())
            ->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/supplier-decision", [
                'decision' => AcceptanceCriterionDecision::Accepted->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.supplier_decision', AcceptanceCriterionDecision::Accepted->value);
    }

    public function test_supplier_decision_rejecting_despite_computed_pass_requires_note(): void
    {
        $ac = $this->makeCriterion(['supplier_passed' => true]);

        $this->actingAs($this->makeApprover())
            ->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/supplier-decision", [
                'decision' => AcceptanceCriterionDecision::Rejected->value,
            ])
            ->assertUnprocessable();
    }

    public function test_supplier_decision_rejecting_despite_computed_pass_succeeds_with_note(): void
    {
        $ac = $this->makeCriterion(['supplier_passed' => true]);

        $this->actingAs($this->makeApprover())
            ->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/supplier-decision", [
                'decision' => AcceptanceCriterionDecision::Rejected->value,
                'note'     => 'Passed automated tests but manual review found a UX issue.',
            ])
            ->assertOk()
            ->assertJsonPath('data.supplier_decision', AcceptanceCriterionDecision::Rejected->value);
    }

    public function test_supplier_decision_accepting_despite_computed_fail_requires_note(): void
    {
        $ac = $this->makeCriterion(['supplier_passed' => false]);

        $this->actingAs($this->makeApprover())
            ->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/supplier-decision", [
                'decision' => AcceptanceCriterionDecision::Accepted->value,
            ])
            ->assertUnprocessable();
    }

    public function test_supplier_decision_sets_decided_by_and_at(): void
    {
        $ac       = $this->makeCriterion(['supplier_passed' => true]);
        $approver = $this->makeApprover();

        $this->actingAs($approver)
            ->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/supplier-decision", [
                'decision' => AcceptanceCriterionDecision::Accepted->value,
            ])
            ->assertOk();

        $fresh = $ac->fresh();
        $this->assertSame($approver->person_id, $fresh->supplier_decided_by);
        $this->assertNotNull($fresh->supplier_decided_at);
    }

    public function test_supplier_decision_forbidden_for_project_manager(): void
    {
        $ac = $this->makeCriterion(['supplier_passed' => true]);

        $this->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/supplier-decision", [
            'decision' => AcceptanceCriterionDecision::Accepted->value,
        ])->assertForbidden();
    }

    public function test_supplier_decision_creates_version_snapshot(): void
    {
        $ac = $this->makeCriterion(['supplier_passed' => true, 'version' => 1]);

        $this->actingAs($this->makeApprover())
            ->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/supplier-decision", [
                'decision' => AcceptanceCriterionDecision::Accepted->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);
    }

    public function test_accepted_at_set_only_once_both_sides_accepted(): void
    {
        $ac       = $this->makeCriterion(['supplier_passed' => true, 'client_passed' => true]);
        $approver = $this->makeApprover();

        $this->actingAs($approver)
            ->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/supplier-decision", [
                'decision' => AcceptanceCriterionDecision::Accepted->value,
            ])->assertOk();

        $this->assertNull($ac->fresh()->accepted_at);

        $this->actingAs($approver)
            ->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/client-decision", [
                'decision' => AcceptanceCriterionDecision::Accepted->value,
            ])->assertOk();

        $this->assertNotNull($ac->fresh()->accepted_at);
    }

    public function test_accepted_at_cleared_when_either_side_rejected(): void
    {
        $ac       = $this->makeCriterion(['supplier_passed' => true, 'client_passed' => true]);
        $approver = $this->makeApprover();

        $this->actingAs($approver)->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/supplier-decision", [
            'decision' => AcceptanceCriterionDecision::Accepted->value,
        ])->assertOk();
        $this->actingAs($approver)->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/client-decision", [
            'decision' => AcceptanceCriterionDecision::Accepted->value,
        ])->assertOk();

        $this->assertNotNull($ac->fresh()->accepted_at);

        $this->actingAs($approver)->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/client-decision", [
            'decision' => AcceptanceCriterionDecision::Rejected->value,
            'note'     => 'Regression found in UAT.',
        ])->assertOk();

        $this->assertNull($ac->fresh()->accepted_at);
    }

    public function test_decision_rejects_pending_value(): void
    {
        $ac = $this->makeCriterion();

        $this->actingAs($this->makeApprover())
            ->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/supplier-decision", [
                'decision' => AcceptanceCriterionDecision::Pending->value,
            ])
            ->assertUnprocessable();
    }

    // -------------------------------------------------------------------------
    // client-decision — mirrors supplier-decision's validation rules
    // -------------------------------------------------------------------------

    public function test_client_decision_accepted_matching_computed_pass_does_not_require_note(): void
    {
        $ac = $this->makeCriterion(['client_passed' => true]);

        $this->actingAs($this->makeApprover())
            ->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/client-decision", [
                'decision' => AcceptanceCriterionDecision::Accepted->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.client_decision', AcceptanceCriterionDecision::Accepted->value);
    }

    public function test_client_decision_rejecting_despite_computed_pass_requires_note(): void
    {
        $ac = $this->makeCriterion(['client_passed' => true]);

        $this->actingAs($this->makeApprover())
            ->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/client-decision", [
                'decision' => AcceptanceCriterionDecision::Rejected->value,
            ])
            ->assertUnprocessable();
    }

    public function test_client_decision_accepting_despite_computed_fail_requires_note(): void
    {
        $ac = $this->makeCriterion(['client_passed' => false]);

        $this->actingAs($this->makeApprover())
            ->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/client-decision", [
                'decision' => AcceptanceCriterionDecision::Accepted->value,
            ])
            ->assertUnprocessable();
    }

    public function test_client_decision_accepting_despite_computed_fail_succeeds_with_note(): void
    {
        $ac = $this->makeCriterion(['client_passed' => false]);

        $this->actingAs($this->makeApprover())
            ->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/client-decision", [
                'decision' => AcceptanceCriterionDecision::Accepted->value,
                'note'     => 'Client accepted based on a manual demo despite the automated test failure.',
            ])
            ->assertOk()
            ->assertJsonPath('data.client_decision', AcceptanceCriterionDecision::Accepted->value);
    }

    // -------------------------------------------------------------------------
    // decision note preservation in version history
    // -------------------------------------------------------------------------

    public function test_decision_note_is_preserved_in_version_history_after_a_later_decision_clears_it(): void
    {
        $ac = $this->makeCriterion(['supplier_passed' => true]);
        $approver = $this->makeApprover();

        $this->actingAs($approver)
            ->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/supplier-decision", [
                'decision' => AcceptanceCriterionDecision::Rejected->value,
                'note'     => 'Found a UX issue not covered by automated tests.',
            ])
            ->assertOk();

        // A later re-decision that matches the computed signal needs no note,
        // and clears the live column — but the original rationale must still
        // be readable in the version that recorded the rejection.
        $this->actingAs($approver)
            ->postJson("/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/supplier-decision", [
                'decision' => AcceptanceCriterionDecision::Accepted->value,
            ])
            ->assertOk();

        $this->assertNull($ac->fresh()->supplier_decision_note);

        $this->assertDatabaseHas('acceptance_criterion_versions', [
            'acceptance_criterion_id' => $ac->id,
            'supplier_decision'       => AcceptanceCriterionDecision::Rejected->value,
            'supplier_decision_note'  => 'Found a UX issue not covered by automated tests.',
        ]);
    }

    // -------------------------------------------------------------------------
    // update — untracked-field-only changes must not bump version
    // -------------------------------------------------------------------------

    public function test_update_with_only_untracked_field_does_not_bump_version_or_snapshot(): void
    {
        $ac = $this->makeCriterion(['version' => 1, 'measurement_method' => 'original method']);

        $this->putJson($this->criterionUrl($ac), ['measurement_method' => 'updated method'])
            ->assertOk()
            ->assertJsonPath('data.version', 1);

        $this->assertDatabaseHas('acceptance_criteria', [
            'id'                  => $ac->id,
            'measurement_method'  => 'updated method',
            'version'             => 1,
        ]);
        $this->assertDatabaseCount('acceptance_criterion_versions', 0);
    }
}
