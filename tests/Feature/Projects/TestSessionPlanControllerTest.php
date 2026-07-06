<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\TeamType;
use App\Enums\TestScenarioStatus;
use App\Enums\TestSessionPlanStatus;
use App\Models\Person;
use App\Models\Project;
use App\Models\TestScenario;
use App\Models\TestSessionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestSessionPlanControllerTest extends TestCase
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
        return "/api/projects/{$this->project->id}/test-session-plans";
    }

    private function planUrl(TestSessionPlan $plan): string
    {
        return "/api/projects/{$this->project->id}/test-session-plans/{$plan->id}";
    }

    private function makePlan(array $attributes = []): TestSessionPlan
    {
        return TestSessionPlan::factory()->create(array_merge([
            'project_id' => $this->project->id,
            'team_type'  => TeamType::Supplier->value,
            'created_by' => $this->person->id,
        ], $attributes));
    }

    private function makeTestableScenario(): TestScenario
    {
        return TestScenario::factory()->testable()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
            'status'     => TestScenarioStatus::Ready->value,
        ]);
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'title'        => 'Supplier regression — Sprint 3',
            'planned_date' => '2026-07-01',
            'team_type'    => TeamType::Supplier->value,
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_lists_plans(): void
    {
        $this->makePlan();
        $this->makePlan();

        $this->getJson($this->indexUrl())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_team_type(): void
    {
        $this->makePlan(['team_type' => TeamType::Supplier->value]);
        $this->makePlan(['team_type' => TeamType::Client->value]);

        $this->getJson($this->indexUrl() . '?team_type=client')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_status(): void
    {
        $this->makePlan(['status' => TestSessionPlanStatus::Draft->value]);
        $this->makePlan(['status' => TestSessionPlanStatus::Active->value]);

        $this->getJson($this->indexUrl() . '?status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_forbidden_for_non_member(): void
    {
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)->getJson($this->indexUrl())->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_plan(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload())
            ->assertCreated()
            ->assertJsonPath('data.ref', 'TSP-001')
            ->assertJsonPath('data.status', TestSessionPlanStatus::Draft->value)
            ->assertJsonPath('data.team_type', TeamType::Supplier->value);

        $this->assertDatabaseHas('test_session_plans', [
            'project_id' => $this->project->id,
            'ref'        => 'TSP-001',
            'created_by' => $this->person->id,
        ]);
    }

    public function test_store_with_ordered_scenarios(): void
    {
        $s1 = $this->makeTestableScenario();
        $s2 = $this->makeTestableScenario();

        $this->postJson($this->indexUrl(), $this->storePayload([
            'scenario_ids' => [$s2->id, $s1->id],
        ]))->assertCreated();

        $plan = TestSessionPlan::first();
        $scenarios = $plan->scenarios()->orderByPivot('order')->pluck('test_scenarios.id');
        $this->assertEquals([$s2->id, $s1->id], $scenarios->toArray());
    }

    public function test_store_rejects_non_testable_scenario(): void
    {
        $notTestable = TestScenario::factory()->create([
            'project_id'  => $this->project->id,
            'created_by'  => $this->person->id,
            'is_testable' => false,
        ]);

        $this->postJson($this->indexUrl(), $this->storePayload([
            'scenario_ids' => [$notTestable->id],
        ]))->assertUnprocessable();
    }

    public function test_store_rejects_scenario_from_another_project(): void
    {
        $other    = Project::factory()->create(['created_by' => $this->person->id]);
        $foreign  = TestScenario::factory()->testable()->create(['project_id' => $other->id, 'created_by' => $this->person->id]);

        $this->postJson($this->indexUrl(), $this->storePayload([
            'scenario_ids' => [$foreign->id],
        ]))->assertUnprocessable();
    }

    public function test_store_requires_title(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['title' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_store_requires_planned_date(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['planned_date' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('planned_date');
    }

    public function test_store_requires_team_type(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['team_type' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('team_type');
    }

    public function test_store_fails_when_title_too_long(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['title' => str_repeat('a', 256)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_store_fails_when_planned_date_invalid(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['planned_date' => 'not-a-date']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('planned_date');
    }

    public function test_store_fails_when_team_type_invalid(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['team_type' => 'vendor']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('team_type');
    }

    public function test_store_fails_when_assignee_id_does_not_exist(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['assignee_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assignee_id');
    }

    public function test_store_creates_plan_with_valid_assignee(): void
    {
        $assignee = Person::factory()->create();

        $this->postJson($this->indexUrl(), $this->storePayload(['assignee_id' => $assignee->id]))
            ->assertCreated()
            ->assertJsonPath('data.assignee.id', $assignee->id);
    }

    public function test_store_fails_when_scenario_id_does_not_exist(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['scenario_ids' => [999999]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scenario_ids.0');
    }

    public function test_store_forbidden_for_observer(): void
    {
        $p = Person::factory()->create();
        $u = User::factory()->create(['person_id' => $p->id]);
        $this->project->members()->create(['person_id' => $p->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($u)->postJson($this->indexUrl(), $this->storePayload())->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_plan_with_scenarios(): void
    {
        $plan = $this->makePlan();
        $scenario = $this->makeTestableScenario();
        $plan->scenarios()->attach($scenario->id, ['order' => 0]);

        $this->getJson($this->planUrl($plan))
            ->assertOk()
            ->assertJsonPath('data.id', $plan->id)
            ->assertJsonCount(1, 'data.scenarios');
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_edits_plan(): void
    {
        $plan = $this->makePlan(['title' => 'Original']);

        $this->putJson($this->planUrl($plan), ['title' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated');
    }

    public function test_update_fails_when_title_empty(): void
    {
        $plan = $this->makePlan(['title' => 'Original']);

        $this->putJson($this->planUrl($plan), ['title' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_update_fails_when_planned_date_invalid(): void
    {
        $plan = $this->makePlan();

        $this->putJson($this->planUrl($plan), ['planned_date' => 'not-a-date'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('planned_date');
    }

    public function test_update_edits_planned_date(): void
    {
        $plan = $this->makePlan();

        $this->putJson($this->planUrl($plan), ['planned_date' => '2026-08-15'])
            ->assertOk()
            ->assertJsonPath('data.planned_date', '2026-08-15T00:00:00.000000Z');
    }

    public function test_update_fails_when_assignee_id_does_not_exist(): void
    {
        $plan = $this->makePlan();

        $this->putJson($this->planUrl($plan), ['assignee_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assignee_id');
    }

    public function test_update_syncs_scenarios(): void
    {
        $plan = $this->makePlan();
        $s1   = $this->makeTestableScenario();
        $s2   = $this->makeTestableScenario();
        $plan->scenarios()->attach($s1->id, ['order' => 0]);

        $this->putJson($this->planUrl($plan), ['scenario_ids' => [$s2->id]])->assertOk();

        $this->assertCount(1, $plan->fresh()->scenarios);
        $this->assertEquals($s2->id, $plan->fresh()->scenarios->first()->id);
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_draft_plan(): void
    {
        $plan = $this->makePlan(['status' => TestSessionPlanStatus::Draft->value]);

        $this->deleteJson($this->planUrl($plan))->assertNoContent();
        $this->assertSoftDeleted('test_session_plans', ['id' => $plan->id]);
    }

    public function test_destroy_forbidden_on_active_plan(): void
    {
        $plan = $this->makePlan(['status' => TestSessionPlanStatus::Active->value]);

        $this->deleteJson($this->planUrl($plan))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // status transitions
    // -------------------------------------------------------------------------

    public function test_activate_transitions_draft_to_active(): void
    {
        $plan = $this->makePlan(['status' => TestSessionPlanStatus::Draft->value]);

        $this->postJson($this->planUrl($plan) . '/activate')
            ->assertOk()
            ->assertJsonPath('data.status', TestSessionPlanStatus::Active->value);
    }

    public function test_activate_returns_409_if_not_draft(): void
    {
        $plan = $this->makePlan(['status' => TestSessionPlanStatus::Active->value]);

        $this->postJson($this->planUrl($plan) . '/activate')->assertStatus(409);
    }

    public function test_complete_transitions_active_to_completed(): void
    {
        $plan = $this->makePlan(['status' => TestSessionPlanStatus::Active->value]);

        $this->postJson($this->planUrl($plan) . '/complete')
            ->assertOk()
            ->assertJsonPath('data.status', TestSessionPlanStatus::Completed->value);
    }

    public function test_complete_returns_409_if_not_active(): void
    {
        $plan = $this->makePlan(['status' => TestSessionPlanStatus::Draft->value]);

        $this->postJson($this->planUrl($plan) . '/complete')->assertStatus(409);
    }

    public function test_cancel_transitions_draft_or_active_to_cancelled(): void
    {
        foreach ([TestSessionPlanStatus::Draft, TestSessionPlanStatus::Active] as $status) {
            $plan = $this->makePlan(['status' => $status->value]);
            $this->postJson($this->planUrl($plan) . '/cancel')
                ->assertOk()
                ->assertJsonPath('data.status', TestSessionPlanStatus::Cancelled->value);
        }
    }

    public function test_cancel_returns_409_on_completed_plan(): void
    {
        $plan = $this->makePlan(['status' => TestSessionPlanStatus::Completed->value]);

        $this->postJson($this->planUrl($plan) . '/cancel')->assertStatus(409);
    }

    // -------------------------------------------------------------------------
    // document
    // -------------------------------------------------------------------------

    public function test_document_returns_structured_plan(): void
    {
        $plan     = $this->makePlan(['title' => 'Sprint 3 regression']);
        $scenario = $this->makeTestableScenario();
        $plan->scenarios()->attach($scenario->id, ['order' => 0]);

        $this->getJson($this->planUrl($plan) . '/document')
            ->assertOk()
            ->assertJsonPath('data.title', 'Sprint 3 regression')
            ->assertJsonCount(1, 'data.scenarios');
    }
}
