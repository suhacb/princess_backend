<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\TestScenarioStatus;
use App\Enums\TestScenarioType;
use App\Models\AcceptanceCriterion;
use App\Models\Person;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\TestCase;
use App\Models\TestScenario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase as BaseTestCase;

class TestScenarioControllerTest extends BaseTestCase
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
        return "/api/projects/{$this->project->id}/test-scenarios";
    }

    private function scenarioUrl(TestScenario $s): string
    {
        return "/api/projects/{$this->project->id}/test-scenarios/{$s->id}";
    }

    private function makeScenario(array $attributes = []): TestScenario
    {
        return TestScenario::factory()->create(array_merge([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ], $attributes));
    }

    private function makeAc(): AcceptanceCriterion
    {
        $requirement = Requirement::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);

        return AcceptanceCriterion::factory()->create([
            'project_id'     => $this->project->id,
            'requirement_id' => $requirement->id,
            'created_by'     => $this->person->id,
        ]);
    }

    private function makeTestCase(TestScenario $scenario): TestCase
    {
        return TestCase::factory()->create([
            'test_scenario_id' => $scenario->id,
            'project_id'       => $this->project->id,
            'created_by'       => $this->person->id,
        ]);
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'type'  => TestScenarioType::Feature->value,
            'title' => 'Login page renders correctly',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_lists_test_scenarios(): void
    {
        $this->makeScenario();
        $this->makeScenario();

        $this->getJson($this->indexUrl())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_type(): void
    {
        $this->makeScenario(['type' => TestScenarioType::Feature->value]);
        $this->makeScenario(['type' => TestScenarioType::E2E->value]);

        $this->getJson($this->indexUrl() . '?type=e2e')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', TestScenarioType::E2E->value);
    }

    public function test_index_filters_by_status(): void
    {
        $this->makeScenario(['status' => TestScenarioStatus::Draft->value]);
        $this->makeScenario(['status' => TestScenarioStatus::Ready->value]);

        $this->getJson($this->indexUrl() . '?status=ready')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_is_testable(): void
    {
        $this->makeScenario(['is_testable' => false]);
        $this->makeScenario(['is_testable' => true]);

        $this->getJson($this->indexUrl() . '?is_testable=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_testable', true);
    }

    public function test_index_filters_by_acceptance_criterion(): void
    {
        $ac       = $this->makeAc();
        $linked   = $this->makeScenario();
        $unlinked = $this->makeScenario();

        $linked->acceptanceCriteria()->attach($ac->id);

        $this->getJson($this->indexUrl() . "?acceptance_criterion_id={$ac->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $linked->id);
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

    public function test_store_creates_test_scenario(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload())
            ->assertCreated()
            ->assertJsonPath('data.ref', 'TS-001')
            ->assertJsonPath('data.type', TestScenarioType::Feature->value)
            ->assertJsonPath('data.status', TestScenarioStatus::Draft->value)
            ->assertJsonPath('data.is_testable', false);

        $this->assertDatabaseHas('test_scenarios', [
            'project_id' => $this->project->id,
            'ref'        => 'TS-001',
            'created_by' => $this->person->id,
        ]);
    }

    public function test_store_with_linked_acceptance_criteria(): void
    {
        $ac1 = $this->makeAc();
        $ac2 = $this->makeAc();

        $this->postJson($this->indexUrl(), $this->storePayload([
            'acceptance_criterion_ids' => [$ac1->id, $ac2->id],
        ]))->assertCreated();

        $scenario = TestScenario::first();
        $this->assertCount(2, $scenario->acceptanceCriteria);
    }

    public function test_store_ref_increments(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload())->assertCreated()->assertJsonPath('data.ref', 'TS-001');
        $this->postJson($this->indexUrl(), $this->storePayload(['title' => 'Second']))->assertCreated()->assertJsonPath('data.ref', 'TS-002');
    }

    public function test_store_rejects_ac_from_another_project(): void
    {
        $other      = Project::factory()->create(['created_by' => $this->person->id]);
        $foreignReq = Requirement::factory()->create(['project_id' => $other->id, 'created_by' => $this->person->id]);
        $foreignAc  = AcceptanceCriterion::factory()->create([
            'project_id'     => $other->id,
            'requirement_id' => $foreignReq->id,
            'created_by'     => $this->person->id,
        ]);

        $this->postJson($this->indexUrl(), $this->storePayload([
            'acceptance_criterion_ids' => [$foreignAc->id],
        ]))->assertUnprocessable();
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

    public function test_store_requires_title(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['title' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_store_requires_type(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['type' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_store_rejects_invalid_type(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['type' => 'bogus']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_scenario_with_relations(): void
    {
        $scenario = $this->makeScenario();
        $ac       = $this->makeAc();
        $scenario->acceptanceCriteria()->attach($ac->id);
        $this->makeTestCase($scenario);

        $this->getJson($this->scenarioUrl($scenario))
            ->assertOk()
            ->assertJsonPath('data.id', $scenario->id)
            ->assertJsonCount(1, 'data.test_cases')
            ->assertJsonCount(1, 'data.acceptance_criteria');
    }

    public function test_show_forbidden_for_non_member(): void
    {
        $scenario = $this->makeScenario();
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->scenarioUrl($scenario))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_edits_scenario(): void
    {
        $scenario = $this->makeScenario(['title' => 'Original']);

        $this->putJson($this->scenarioUrl($scenario), ['title' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated');
    }

    public function test_update_syncs_acceptance_criteria(): void
    {
        $scenario = $this->makeScenario();
        $ac1      = $this->makeAc();
        $ac2      = $this->makeAc();
        $scenario->acceptanceCriteria()->attach($ac1->id);

        $this->putJson($this->scenarioUrl($scenario), [
            'acceptance_criterion_ids' => [$ac2->id],
        ])->assertOk();

        $this->assertCount(1, $scenario->fresh()->acceptanceCriteria);
        $this->assertEquals($ac2->id, $scenario->fresh()->acceptanceCriteria->first()->id);
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_draft_scenario(): void
    {
        $scenario = $this->makeScenario(['status' => TestScenarioStatus::Draft->value]);

        $this->deleteJson($this->scenarioUrl($scenario))->assertNoContent();
        $this->assertSoftDeleted('test_scenarios', ['id' => $scenario->id]);
    }

    public function test_destroy_forbidden_on_ready_scenario(): void
    {
        $scenario = $this->makeScenario(['status' => TestScenarioStatus::Ready->value]);

        $this->deleteJson($this->scenarioUrl($scenario))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // status transitions
    // -------------------------------------------------------------------------

    public function test_ready_transitions_draft_to_ready(): void
    {
        $scenario = $this->makeScenario(['status' => TestScenarioStatus::Draft->value]);
        $this->makeTestCase($scenario);

        $this->postJson($this->scenarioUrl($scenario) . '/ready')
            ->assertOk()
            ->assertJsonPath('data.status', TestScenarioStatus::Ready->value);
    }

    public function test_ready_requires_at_least_one_test_case(): void
    {
        $scenario = $this->makeScenario(['status' => TestScenarioStatus::Draft->value]);

        $this->postJson($this->scenarioUrl($scenario) . '/ready')
            ->assertUnprocessable();
    }

    public function test_ready_returns_409_if_not_draft(): void
    {
        $scenario = $this->makeScenario(['status' => TestScenarioStatus::Ready->value]);
        $this->makeTestCase($scenario);

        $this->postJson($this->scenarioUrl($scenario) . '/ready')
            ->assertStatus(409);
    }

    public function test_obsolete_transitions_ready_to_obsolete(): void
    {
        $scenario = $this->makeScenario(['status' => TestScenarioStatus::Ready->value]);

        $this->postJson($this->scenarioUrl($scenario) . '/obsolete')
            ->assertOk()
            ->assertJsonPath('data.status', TestScenarioStatus::Obsolete->value);
    }

    public function test_obsolete_returns_409_if_not_ready(): void
    {
        $scenario = $this->makeScenario(['status' => TestScenarioStatus::Draft->value]);

        $this->postJson($this->scenarioUrl($scenario) . '/obsolete')
            ->assertStatus(409);
    }

    public function test_reopen_transitions_obsolete_to_draft(): void
    {
        $scenario = $this->makeScenario(['status' => TestScenarioStatus::Obsolete->value]);

        $this->postJson($this->scenarioUrl($scenario) . '/reopen')
            ->assertOk()
            ->assertJsonPath('data.status', TestScenarioStatus::Draft->value);
    }

    public function test_reopen_returns_409_if_not_obsolete(): void
    {
        $scenario = $this->makeScenario(['status' => TestScenarioStatus::Draft->value]);

        $this->postJson($this->scenarioUrl($scenario) . '/reopen')
            ->assertStatus(409);
    }

    public function test_status_transitions_forbidden_for_read_only_role(): void
    {
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);

        $scenario = $this->makeScenario(['status' => TestScenarioStatus::Ready->value]);

        $this->actingAs($observer)
            ->postJson($this->scenarioUrl($scenario) . '/obsolete')
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // mark testable / not testable
    // -------------------------------------------------------------------------

    public function test_mark_testable_sets_is_testable(): void
    {
        $scenario = $this->makeScenario(['is_testable' => false]);

        $this->postJson($this->scenarioUrl($scenario) . '/mark-testable', [
            'testable_notes' => 'Login feature is deployed to staging.',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_testable', true)
            ->assertJsonPath('data.testable_notes', 'Login feature is deployed to staging.');
    }

    public function test_mark_testable_works_without_notes(): void
    {
        $scenario = $this->makeScenario(['is_testable' => false]);

        $this->postJson($this->scenarioUrl($scenario) . '/mark-testable')
            ->assertOk()
            ->assertJsonPath('data.is_testable', true);
    }

    public function test_mark_not_testable_unsets_is_testable(): void
    {
        $scenario = $this->makeScenario([
            'is_testable'    => true,
            'testable_notes' => 'Some notes',
        ]);

        $this->postJson($this->scenarioUrl($scenario) . '/mark-not-testable')
            ->assertOk()
            ->assertJsonPath('data.is_testable', false)
            ->assertJsonPath('data.testable_notes', null);
    }

    public function test_mark_testable_forbidden_for_observer(): void
    {
        $scenario       = $this->makeScenario();
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($observer)
            ->postJson($this->scenarioUrl($scenario) . '/mark-testable')
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // document endpoint
    // -------------------------------------------------------------------------

    public function test_document_returns_structured_response(): void
    {
        $scenario = $this->makeScenario([
            'title'        => 'Full login flow',
            'description'  => 'Tests the login page',
            'preconditions' => 'User exists in system',
        ]);
        $ac = $this->makeAc();
        $scenario->acceptanceCriteria()->attach($ac->id);
        $this->makeTestCase($scenario);

        $this->getJson($this->scenarioUrl($scenario) . '/document')
            ->assertOk()
            ->assertJsonPath('data.title', 'Full login flow')
            ->assertJsonPath('data.preconditions', 'User exists in system')
            ->assertJsonCount(1, 'data.test_cases')
            ->assertJsonCount(1, 'data.acceptance_criteria');
    }

    public function test_document_forbidden_for_non_member(): void
    {
        $scenario = $this->makeScenario();
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->scenarioUrl($scenario) . '/document')
            ->assertForbidden();
    }
}
