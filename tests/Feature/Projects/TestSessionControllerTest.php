<?php

namespace Tests\Feature\Projects;

use App\Enums\AcceptanceCriterionStatus;
use App\Enums\ProjectRole;
use App\Enums\TeamType;
use App\Enums\TestResultStatus;
use App\Enums\TestScenarioStatus;
use App\Enums\TestSessionPlanStatus;
use App\Enums\TestSessionStatus;
use App\Models\AcceptanceCriterion;
use App\Models\Person;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\TestScenario;
use App\Models\TestSession;
use App\Models\TestSessionPlan;
use App\Models\TestSessionResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestSessionControllerTest extends TestCase
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
        return "/api/projects/{$this->project->id}/test-sessions";
    }

    private function sessionUrl(TestSession $session): string
    {
        return "/api/projects/{$this->project->id}/test-sessions/{$session->id}";
    }

    private function makeSession(array $attributes = []): TestSession
    {
        return TestSession::factory()->create(array_merge([
            'project_id' => $this->project->id,
            'tester_id'  => $this->person->id,
            'team_type'  => TeamType::Supplier->value,
            'created_by' => $this->person->id,
        ], $attributes));
    }

    private function makeTestableScenario(array $attrs = []): TestScenario
    {
        return TestScenario::factory()->testable()->create(array_merge([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
            'status'     => TestScenarioStatus::Ready->value,
        ], $attrs));
    }

    private function makeAc(Requirement $requirement): AcceptanceCriterion
    {
        return AcceptanceCriterion::factory()->create([
            'project_id'     => $this->project->id,
            'requirement_id' => $requirement->id,
            'created_by'     => $this->person->id,
        ]);
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'title'        => 'Supplier sprint 3 session',
            'session_date' => '2026-07-01',
            'tester_id'    => $this->person->id,
            'team_type'    => TeamType::Supplier->value,
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_lists_sessions(): void
    {
        $this->makeSession();
        $this->makeSession();

        $this->getJson($this->indexUrl())->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_team_type(): void
    {
        $this->makeSession(['team_type' => TeamType::Supplier->value]);
        $this->makeSession(['team_type' => TeamType::Client->value]);

        $this->getJson($this->indexUrl() . '?team_type=client')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_status(): void
    {
        $this->makeSession(['status' => TestSessionStatus::Planned->value]);
        $this->makeSession(['status' => TestSessionStatus::InProgress->value]);

        $this->getJson($this->indexUrl() . '?status=in_progress')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_index_forbidden_for_non_member(): void
    {
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)->getJson($this->indexUrl())->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_session(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload())
            ->assertCreated()
            ->assertJsonPath('data.ref', 'TSR-001')
            ->assertJsonPath('data.status', TestSessionStatus::Planned->value)
            ->assertJsonPath('data.team_type', TeamType::Supplier->value);

        $this->assertDatabaseHas('test_sessions', [
            'project_id' => $this->project->id,
            'ref'        => 'TSR-001',
            'created_by' => $this->person->id,
        ]);
    }

    public function test_store_prepopulates_results_from_plan(): void
    {
        $plan = TestSessionPlan::factory()->create([
            'project_id' => $this->project->id,
            'team_type'  => TeamType::Supplier->value,
            'created_by' => $this->person->id,
        ]);
        $s1 = $this->makeTestableScenario();
        $s2 = $this->makeTestableScenario();
        $plan->scenarios()->attach([$s1->id => ['order' => 0], $s2->id => ['order' => 1]]);

        $this->postJson($this->indexUrl(), $this->storePayload([
            'test_session_plan_id' => $plan->id,
        ]))->assertCreated();

        $session = TestSession::first();
        $this->assertCount(2, $session->results);
        $this->assertTrue($session->results->every(
            fn ($r) => $r->result->value === TestResultStatus::NotRun->value
        ));
    }

    public function test_store_rejects_tester_not_in_project(): void
    {
        $outsider = Person::factory()->create();

        $this->postJson($this->indexUrl(), $this->storePayload(['tester_id' => $outsider->id]))
            ->assertUnprocessable();
    }

    public function test_store_rejects_team_type_mismatch_with_plan(): void
    {
        $plan = TestSessionPlan::factory()->create([
            'project_id' => $this->project->id,
            'team_type'  => TeamType::Supplier->value,
            'created_by' => $this->person->id,
        ]);

        $this->postJson($this->indexUrl(), $this->storePayload([
            'team_type'           => TeamType::Client->value,
            'test_session_plan_id' => $plan->id,
        ]))->assertUnprocessable();
    }

    public function test_store_rejects_plan_from_another_project(): void
    {
        $other = Project::factory()->create(['created_by' => $this->person->id]);
        $plan  = TestSessionPlan::factory()->create([
            'project_id' => $other->id,
            'team_type'  => TeamType::Supplier->value,
            'created_by' => $this->person->id,
        ]);

        $this->postJson($this->indexUrl(), $this->storePayload([
            'test_session_plan_id' => $plan->id,
        ]))->assertUnprocessable();
    }

    // -------------------------------------------------------------------------
    // show / update / destroy
    // -------------------------------------------------------------------------

    public function test_show_returns_session_with_results(): void
    {
        $session  = $this->makeSession();
        $scenario = $this->makeTestableScenario();
        TestSessionResult::create([
            'test_session_id'  => $session->id,
            'test_scenario_id' => $scenario->id,
            'result'           => TestResultStatus::NotRun->value,
        ]);

        $this->getJson($this->sessionUrl($session))
            ->assertOk()
            ->assertJsonCount(1, 'data.results');
    }

    public function test_update_edits_session(): void
    {
        $session = $this->makeSession(['title' => 'Original']);

        $this->putJson($this->sessionUrl($session), ['title' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated');
    }

    public function test_destroy_deletes_planned_session(): void
    {
        $session = $this->makeSession(['status' => TestSessionStatus::Planned->value]);

        $this->deleteJson($this->sessionUrl($session))->assertNoContent();
        $this->assertSoftDeleted('test_sessions', ['id' => $session->id]);
    }

    public function test_destroy_forbidden_on_in_progress_session(): void
    {
        $session = $this->makeSession(['status' => TestSessionStatus::InProgress->value]);

        $this->deleteJson($this->sessionUrl($session))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // lifecycle transitions
    // -------------------------------------------------------------------------

    public function test_start_transitions_planned_to_in_progress(): void
    {
        $session = $this->makeSession(['status' => TestSessionStatus::Planned->value]);

        $this->postJson($this->sessionUrl($session) . '/start')
            ->assertOk()
            ->assertJsonPath('data.status', TestSessionStatus::InProgress->value);
    }

    public function test_start_returns_409_if_not_planned(): void
    {
        $session = $this->makeSession(['status' => TestSessionStatus::InProgress->value]);

        $this->postJson($this->sessionUrl($session) . '/start')->assertStatus(409);
    }

    public function test_complete_transitions_in_progress_to_completed(): void
    {
        $session = $this->makeSession(['status' => TestSessionStatus::InProgress->value]);

        $this->postJson($this->sessionUrl($session) . '/complete')
            ->assertOk()
            ->assertJsonPath('data.status', TestSessionStatus::Completed->value);
    }

    public function test_complete_returns_409_if_not_in_progress(): void
    {
        $session = $this->makeSession(['status' => TestSessionStatus::Planned->value]);

        $this->postJson($this->sessionUrl($session) . '/complete')->assertStatus(409);
    }

    public function test_cancel_transitions_planned_or_in_progress_to_cancelled(): void
    {
        foreach ([TestSessionStatus::Planned, TestSessionStatus::InProgress] as $status) {
            $session = $this->makeSession(['status' => $status->value]);
            $this->postJson($this->sessionUrl($session) . '/cancel')
                ->assertOk()
                ->assertJsonPath('data.status', TestSessionStatus::Cancelled->value);
        }
    }

    public function test_cancel_returns_409_on_completed_session(): void
    {
        $session = $this->makeSession(['status' => TestSessionStatus::Completed->value]);

        $this->postJson($this->sessionUrl($session) . '/cancel')->assertStatus(409);
    }

    // -------------------------------------------------------------------------
    // update result
    // -------------------------------------------------------------------------

    public function test_update_result_records_pass(): void
    {
        $session  = $this->makeSession();
        $scenario = $this->makeTestableScenario();
        TestSessionResult::create([
            'test_session_id'  => $session->id,
            'test_scenario_id' => $scenario->id,
            'result'           => TestResultStatus::NotRun->value,
        ]);

        $this->putJson(
            "/api/projects/{$this->project->id}/test-sessions/{$session->id}/results/{$scenario->id}",
            ['result' => 'pass']
        )
            ->assertOk()
            ->assertJsonPath('data.result', TestResultStatus::Pass->value);
    }

    public function test_update_result_rejects_scenario_not_in_session(): void
    {
        $session  = $this->makeSession();
        $scenario = $this->makeTestableScenario();

        $this->putJson(
            "/api/projects/{$this->project->id}/test-sessions/{$session->id}/results/{$scenario->id}",
            ['result' => 'pass']
        )->assertUnprocessable();
    }

    public function test_tester_can_update_own_session_result(): void
    {
        $testerPerson = Person::factory()->create();
        $testerUser   = User::factory()->create(['person_id' => $testerPerson->id]);
        $this->project->members()->create(['person_id' => $testerPerson->id, 'role' => ProjectRole::TeamMember->value]);

        $session  = $this->makeSession(['tester_id' => $testerPerson->id]);
        $scenario = $this->makeTestableScenario();
        TestSessionResult::create([
            'test_session_id'  => $session->id,
            'test_scenario_id' => $scenario->id,
            'result'           => TestResultStatus::NotRun->value,
        ]);

        $this->actingAs($testerUser)
            ->putJson(
                "/api/projects/{$this->project->id}/test-sessions/{$session->id}/results/{$scenario->id}",
                ['result' => 'pass']
            )->assertOk();
    }

    public function test_non_tester_without_qa_manage_cannot_update_result(): void
    {
        $otherPerson = Person::factory()->create();
        $otherUser   = User::factory()->create(['person_id' => $otherPerson->id]);
        $this->project->members()->create(['person_id' => $otherPerson->id, 'role' => ProjectRole::Observer->value]);

        $session  = $this->makeSession();
        $scenario = $this->makeTestableScenario();
        TestSessionResult::create([
            'test_session_id'  => $session->id,
            'test_scenario_id' => $scenario->id,
            'result'           => TestResultStatus::NotRun->value,
        ]);

        $this->actingAs($otherUser)
            ->putJson(
                "/api/projects/{$this->project->id}/test-sessions/{$session->id}/results/{$scenario->id}",
                ['result' => 'pass']
            )->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // AC recomputation on complete
    // -------------------------------------------------------------------------

    public function test_complete_sets_supplier_passed_on_ac_when_all_scenarios_pass(): void
    {
        $requirement = Requirement::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);
        $ac       = $this->makeAc($requirement);
        $scenario = $this->makeTestableScenario();
        $scenario->acceptanceCriteria()->attach($ac->id);

        $session = $this->makeSession([
            'status'    => TestSessionStatus::InProgress->value,
            'team_type' => TeamType::Supplier->value,
        ]);
        TestSessionResult::create([
            'test_session_id'  => $session->id,
            'test_scenario_id' => $scenario->id,
            'result'           => TestResultStatus::Pass->value,
        ]);

        $this->postJson($this->sessionUrl($session) . '/complete')->assertOk();

        $this->assertDatabaseHas('acceptance_criteria', [
            'id'              => $ac->id,
            'supplier_passed' => true,
            'client_passed'   => false,
        ]);
        $this->assertNull($ac->fresh()->accepted_at);
    }

    public function test_complete_sets_accepted_at_when_both_sides_pass(): void
    {
        $requirement = Requirement::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);
        $ac       = $this->makeAc($requirement);
        $scenario = $this->makeTestableScenario();
        $scenario->acceptanceCriteria()->attach($ac->id);

        // Simulate a previous completed supplier session that passed
        $supplierSession = $this->makeSession([
            'status'    => TestSessionStatus::Completed->value,
            'team_type' => TeamType::Supplier->value,
        ]);
        TestSessionResult::create([
            'test_session_id'  => $supplierSession->id,
            'test_scenario_id' => $scenario->id,
            'result'           => TestResultStatus::Pass->value,
        ]);

        // Now run the client session and complete it
        $clientSession = $this->makeSession([
            'status'    => TestSessionStatus::InProgress->value,
            'team_type' => TeamType::Client->value,
        ]);
        TestSessionResult::create([
            'test_session_id'  => $clientSession->id,
            'test_scenario_id' => $scenario->id,
            'result'           => TestResultStatus::Pass->value,
        ]);

        $this->postJson($this->sessionUrl($clientSession) . '/complete')->assertOk();

        $freshAc = $ac->fresh();
        $this->assertTrue($freshAc->supplier_passed);
        $this->assertTrue($freshAc->client_passed);
        $this->assertNotNull($freshAc->accepted_at);
    }

    public function test_complete_clears_accepted_at_when_fail_result(): void
    {
        $requirement = Requirement::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);
        $ac = $this->makeAc($requirement);
        $ac->update([
            'supplier_passed' => true,
            'client_passed'   => true,
            'accepted_at'     => now()->subDay(),
        ]);

        $scenario = $this->makeTestableScenario();
        $scenario->acceptanceCriteria()->attach($ac->id);

        $session = $this->makeSession([
            'status'    => TestSessionStatus::InProgress->value,
            'team_type' => TeamType::Supplier->value,
        ]);
        TestSessionResult::create([
            'test_session_id'  => $session->id,
            'test_scenario_id' => $scenario->id,
            'result'           => TestResultStatus::Fail->value,
        ]);

        $this->postJson($this->sessionUrl($session) . '/complete')->assertOk();

        $freshAc = $ac->fresh();
        $this->assertFalse($freshAc->supplier_passed);
        $this->assertNull($freshAc->accepted_at);
    }

    public function test_complete_creates_issue_for_fail_result(): void
    {
        $scenario = $this->makeTestableScenario(['title' => 'Login test']);
        $session  = $this->makeSession(['status' => TestSessionStatus::InProgress->value]);
        TestSessionResult::create([
            'test_session_id'  => $session->id,
            'test_scenario_id' => $scenario->id,
            'result'           => TestResultStatus::Fail->value,
            'defect_ref'       => 'BUG-123',
        ]);

        $this->postJson($this->sessionUrl($session) . '/complete')->assertOk();

        $this->assertDatabaseHas('issues', [
            'project_id' => $this->project->id,
            'title'      => 'Test failure: Login test',
        ]);
    }

    // -------------------------------------------------------------------------
    // report
    // -------------------------------------------------------------------------

    public function test_report_returns_structured_report(): void
    {
        $session  = $this->makeSession(['title' => 'My session']);
        $scenario = $this->makeTestableScenario();
        TestSessionResult::create([
            'test_session_id'  => $session->id,
            'test_scenario_id' => $scenario->id,
            'result'           => TestResultStatus::Pass->value,
        ]);

        $this->getJson($this->sessionUrl($session) . '/report')
            ->assertOk()
            ->assertJsonPath('data.title', 'My session')
            ->assertJsonCount(1, 'data.results');
    }
}
