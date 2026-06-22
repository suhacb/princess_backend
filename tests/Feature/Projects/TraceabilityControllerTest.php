<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\RequirementType;
use App\Enums\TeamType;
use App\Enums\TestResultStatus;
use App\Enums\TestScenarioStatus;
use App\Enums\TestSessionStatus;
use App\Models\AcceptanceCriterion;
use App\Models\Person;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\TestScenario;
use App\Models\TestSession;
use App\Models\TestSessionResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TraceabilityControllerTest extends TestCase
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

    private function url(): string
    {
        return "/api/projects/{$this->project->id}/traceability";
    }

    private function makeClassicReq(array $attrs = []): Requirement
    {
        return Requirement::factory()->create(array_merge([
            'project_id' => $this->project->id,
            'type'       => RequirementType::Classic->value,
            'created_by' => $this->person->id,
        ], $attrs));
    }

    private function makeAc(Requirement $req, array $attrs = []): AcceptanceCriterion
    {
        return AcceptanceCriterion::factory()->create(array_merge([
            'project_id'     => $this->project->id,
            'requirement_id' => $req->id,
            'created_by'     => $this->person->id,
        ], $attrs));
    }

    private function makeScenario(array $attrs = []): TestScenario
    {
        return TestScenario::factory()->testable()->create(array_merge([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
            'status'     => TestScenarioStatus::Ready->value,
        ], $attrs));
    }

    private function makeCompletedSession(string $teamType): TestSession
    {
        return TestSession::factory()->completed()->create([
            'project_id' => $this->project->id,
            'tester_id'  => $this->person->id,
            'team_type'  => $teamType,
            'created_by' => $this->person->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // basic structure
    // -------------------------------------------------------------------------

    public function test_returns_classic_requirements_with_acs(): void
    {
        $req = $this->makeClassicReq();
        $this->makeAc($req);

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', RequirementType::Classic->value)
            ->assertJsonCount(1, 'data.0.acceptance_criteria');
    }

    public function test_returns_epics_with_nested_user_stories(): void
    {
        $epic = Requirement::factory()->epic()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);
        $story = Requirement::factory()->userStory()->create([
            'project_id' => $this->project->id,
            'parent_id'  => $epic->id,
            'created_by' => $this->person->id,
        ]);

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', RequirementType::Epic->value)
            ->assertJsonCount(1, 'data.0.user_stories');
    }

    public function test_user_stories_not_shown_at_top_level(): void
    {
        $epic = Requirement::factory()->epic()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);
        Requirement::factory()->userStory()->create([
            'project_id' => $this->project->id,
            'parent_id'  => $epic->id,
            'created_by' => $this->person->id,
        ]);

        // Should show 1 item (the epic), not 2
        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_includes_test_scenarios_with_latest_results(): void
    {
        $req      = $this->makeClassicReq();
        $ac       = $this->makeAc($req);
        $scenario = $this->makeScenario();
        $scenario->acceptanceCriteria()->attach($ac->id);

        $session = $this->makeCompletedSession(TeamType::Supplier->value);
        TestSessionResult::create([
            'test_session_id'  => $session->id,
            'test_scenario_id' => $scenario->id,
            'result'           => TestResultStatus::Pass->value,
        ]);

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.0.acceptance_criteria.0.test_scenarios.0.latest_supplier_result', 'pass')
            ->assertJsonPath('data.0.acceptance_criteria.0.test_scenarios.0.latest_client_result', null);
    }

    // -------------------------------------------------------------------------
    // derived_status
    // -------------------------------------------------------------------------

    public function test_derived_status_is_not_tested_when_no_scenarios(): void
    {
        $req = $this->makeClassicReq();
        $this->makeAc($req);

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.0.derived_status', 'not_tested');
    }

    public function test_derived_status_is_not_tested_when_no_results_yet(): void
    {
        $req      = $this->makeClassicReq();
        $ac       = $this->makeAc($req);
        $scenario = $this->makeScenario();
        $scenario->acceptanceCriteria()->attach($ac->id);

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.0.derived_status', 'not_tested');
    }

    public function test_derived_status_is_failing_when_any_scenario_fails(): void
    {
        $req      = $this->makeClassicReq();
        $ac       = $this->makeAc($req);
        $scenario = $this->makeScenario();
        $scenario->acceptanceCriteria()->attach($ac->id);

        $session = $this->makeCompletedSession(TeamType::Supplier->value);
        TestSessionResult::create([
            'test_session_id'  => $session->id,
            'test_scenario_id' => $scenario->id,
            'result'           => TestResultStatus::Fail->value,
        ]);

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.0.derived_status', 'failing');
    }

    public function test_derived_status_is_covered_when_all_acs_accepted(): void
    {
        $req = $this->makeClassicReq();
        $ac  = $this->makeAc($req, ['accepted_at' => now()]);
        $scenario = $this->makeScenario();
        $scenario->acceptanceCriteria()->attach($ac->id);

        $session = $this->makeCompletedSession(TeamType::Supplier->value);
        TestSessionResult::create([
            'test_session_id'  => $session->id,
            'test_scenario_id' => $scenario->id,
            'result'           => TestResultStatus::Pass->value,
        ]);

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.0.derived_status', 'covered');
    }

    public function test_derived_status_is_partial_when_some_acs_accepted(): void
    {
        $req  = $this->makeClassicReq();
        $ac1  = $this->makeAc($req, ['accepted_at' => now()]);
        $ac2  = $this->makeAc($req);

        $s1 = $this->makeScenario();
        $s2 = $this->makeScenario();
        $s1->acceptanceCriteria()->attach($ac1->id);
        $s2->acceptanceCriteria()->attach($ac2->id);

        $session = $this->makeCompletedSession(TeamType::Supplier->value);
        TestSessionResult::create([
            'test_session_id'  => $session->id,
            'test_scenario_id' => $s1->id,
            'result'           => TestResultStatus::Pass->value,
        ]);

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.0.derived_status', 'partial');
    }

    // -------------------------------------------------------------------------
    // authorization
    // -------------------------------------------------------------------------

    public function test_forbidden_for_non_member(): void
    {
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)->getJson($this->url())->assertForbidden();
    }
}
