<?php

namespace App\Http\Controllers;

use App\Enums\RequirementType;
use App\Enums\TestSessionStatus;
use App\Models\AcceptanceCriterion;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\TestCase;
use App\Models\TestScenario;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @tags Traceability
 */
class TraceabilityController extends Controller
{
    /** Per scenario id: ['supplier' => result|null, 'client' => result|null], latest completed session first. */
    private array $scenarioTeamResults = [];

    /** Per scenario id: latest result across teams, from the scenario-level aggregate row (test_case_id IS NULL). */
    private array $scenarioLatestResults = [];

    /** Per test case id: latest result across teams, from test-case-level rows only. */
    private array $testCaseLatestResults = [];

    /**
     * Return the full requirements traceability matrix: requirements → acceptance criteria → test scenarios → test cases → results, plus a coverage stats rollup.
     *
     * @response {"data": [{"id": 1, "ref": "REQ-001", "acceptance_criteria": [{"ref": "AC-001", "test_scenarios": [{"ref": "TS-001", "test_cases": [{"id": 1, "title": "...", "priority": "high", "type": "positive"}]}]}]}], "stats": {"acs_total": 42, "acs_with_test": 30, "acs_with_test_pct": 71.4, "test_cases_total": 120, "test_cases_passed": 95, "test_cases_passed_pct": 79.2}}
     */
    public function index(Project $project): JsonResponse
    {
        $this->authorize('viewAny', [\App\Models\Requirement::class, $project]);

        // Load top-level requirements (non-user-stories): classics and epics
        $requirements = $project->requirements()
            ->whereIn('type', [RequirementType::Classic->value, RequirementType::Epic->value])
            ->whereNull('parent_id')
            ->with([
                'acceptanceCriteria.testScenarios.testCases',
                'children.acceptanceCriteria.testScenarios.testCases',
            ])
            ->get();

        $allAcs = $requirements->flatMap(fn ($req) => $req->type === RequirementType::Epic
            ? $req->children->flatMap->acceptanceCriteria
            : $req->acceptanceCriteria);

        $allScenarios = $allAcs->flatMap->testScenarios->unique('id')->values();
        $allTestCases = $allScenarios->flatMap->testCases->unique('id')->values();

        $this->loadScenarioResults($allScenarios->pluck('id'));
        $this->loadTestCaseResults($allTestCases->pluck('id'));

        return response()->json([
            'data'  => $requirements->map(fn ($req) => $this->mapRequirement($req)),
            'stats' => $this->computeStats($allAcs, $allTestCases),
        ]);
    }

    /**
     * Batches the scenario-level "latest result per team" lookup for every scenario in the tree into
     * a single query, instead of the 2-per-team-per-scenario raw queries mapScenario()/derivedStatusFromAcs()
     * used to run independently (TestScenario::latestResultForTeam() per call).
     */
    private function loadScenarioResults(Collection $scenarioIds): void
    {
        if ($scenarioIds->isEmpty()) {
            return;
        }

        $rows = DB::table('test_session_results')
            ->join('test_sessions', 'test_session_results.test_session_id', '=', 'test_sessions.id')
            ->whereIn('test_session_results.test_scenario_id', $scenarioIds)
            ->whereNull('test_session_results.test_case_id')
            ->where('test_sessions.status', TestSessionStatus::Completed->value)
            ->orderByDesc('test_sessions.session_date')
            ->orderByDesc('test_sessions.id')
            ->select('test_session_results.test_scenario_id', 'test_session_results.result', 'test_sessions.team_type')
            ->get();

        foreach ($rows as $row) {
            // Ordered latest-first, so the first row seen per key is the latest one.
            $this->scenarioTeamResults[$row->test_scenario_id][$row->team_type] ??= $row->result;
            $this->scenarioLatestResults[$row->test_scenario_id] ??= $row->result;
        }
    }

    /** Latest result per test case, team-agnostic, from test-case-level rows only (no scenario fallback here). */
    private function loadTestCaseResults(Collection $testCaseIds): void
    {
        if ($testCaseIds->isEmpty()) {
            return;
        }

        $rows = DB::table('test_session_results')
            ->join('test_sessions', 'test_session_results.test_session_id', '=', 'test_sessions.id')
            ->whereIn('test_session_results.test_case_id', $testCaseIds)
            ->where('test_sessions.status', TestSessionStatus::Completed->value)
            ->orderByDesc('test_sessions.session_date')
            ->orderByDesc('test_sessions.id')
            ->select('test_session_results.test_case_id', 'test_session_results.result')
            ->get();

        foreach ($rows as $row) {
            $this->testCaseLatestResults[$row->test_case_id] ??= $row->result;
        }
    }

    private function mapRequirement(Requirement $req): array
    {
        if ($req->type === RequirementType::Epic) {
            $userStories = $req->children->map(fn ($us) => $this->mapUserStory($us));
            return [
                'id'           => $req->id,
                'ref'          => $req->ref,
                'type'         => $req->type,
                'title'        => $req->title,
                'priority'     => $req->priority,
                'status'       => $req->status,
                'user_stories' => $userStories,
                'derived_status' => $this->derivedStatusFromUserStories($req->children),
            ];
        }

        $acs = $req->acceptanceCriteria->map(fn ($ac) => $this->mapAc($ac));
        return [
            'id'                 => $req->id,
            'ref'                => $req->ref,
            'type'               => $req->type,
            'title'              => $req->title,
            'priority'           => $req->priority,
            'status'             => $req->status,
            'acceptance_criteria' => $acs,
            'derived_status'     => $this->derivedStatusFromAcs($req->acceptanceCriteria),
        ];
    }

    private function mapUserStory(Requirement $us): array
    {
        $acs = $us->acceptanceCriteria->map(fn ($ac) => $this->mapAc($ac));
        return [
            'id'                 => $us->id,
            'ref'                => $us->ref,
            'title'              => $us->title,
            'role'               => $us->role,
            'status'             => $us->status,
            'acceptance_criteria' => $acs,
            'derived_status'     => $this->derivedStatusFromAcs($us->acceptanceCriteria),
        ];
    }

    private function mapAc(AcceptanceCriterion $ac): array
    {
        $scenarios = $ac->testScenarios->map(fn ($s) => $this->mapScenario($s));
        return [
            'id'              => $ac->id,
            'ref'             => $ac->ref,
            'description'     => $ac->description,
            'supplier_passed' => $ac->supplier_passed,
            'client_passed'   => $ac->client_passed,
            'accepted_at'     => $ac->accepted_at,
            'test_scenarios'  => $scenarios,
        ];
    }

    private function mapScenario(TestScenario $s): array
    {
        return [
            'id'                    => $s->id,
            'ref'                   => $s->ref,
            'title'                 => $s->title,
            'type'                  => $s->type,
            'is_testable'           => $s->is_testable,
            'latest_supplier_result' => $this->scenarioTeamResults[$s->id]['supplier'] ?? null,
            'latest_client_result'  => $this->scenarioTeamResults[$s->id]['client'] ?? null,
            'test_cases'            => $s->testCases->map(fn (TestCase $tc) => [
                'id'       => $tc->id,
                'title'    => $tc->title,
                'priority' => $tc->priority?->value,
                'type'     => $tc->type?->value,
            ]),
        ];
    }

    private function derivedStatusFromAcs($acs): string
    {
        if ($acs->isEmpty()) {
            return 'not_tested';
        }

        $allScenarios = $acs->flatMap->testScenarios;

        if ($allScenarios->isEmpty()) {
            return 'not_tested';
        }

        $hasAnyResult = $allScenarios->some(
            fn ($s) => ($this->scenarioTeamResults[$s->id]['supplier'] ?? null) !== null
                || ($this->scenarioTeamResults[$s->id]['client'] ?? null) !== null
        );

        if (! $hasAnyResult) {
            return 'not_tested';
        }

        $hasFail = $allScenarios->some(
            fn ($s) => ($this->scenarioTeamResults[$s->id]['supplier'] ?? null) === 'fail'
                || ($this->scenarioTeamResults[$s->id]['client'] ?? null) === 'fail'
        );

        if ($hasFail) {
            return 'failing';
        }

        $allAccepted = $acs->every(fn ($ac) => $ac->accepted_at !== null);
        if ($allAccepted) {
            return 'covered';
        }

        $anyAccepted = $acs->some(fn ($ac) => $ac->accepted_at !== null);
        if ($anyAccepted) {
            return 'partial';
        }

        return 'not_tested';
    }

    private function derivedStatusFromUserStories($userStories): string
    {
        if ($userStories->isEmpty()) {
            return 'not_tested';
        }

        $allAcs = $userStories->flatMap->acceptanceCriteria;
        return $this->derivedStatusFromAcs($allAcs);
    }

    /**
     * acs_with_test is structural (AC has >=1 linked test scenario), independent of execution.
     * test_cases_passed is execution-based: latest result per test case, falling back to the
     * test case's scenario-level aggregate result when no test-case-level row exists yet
     * (covers history from before the test_case_id column was added).
     */
    private function computeStats(Collection $allAcs, Collection $allTestCases): array
    {
        $acsTotal = $allAcs->count();
        $acsWithTest = $allAcs->filter(fn (AcceptanceCriterion $ac) => $ac->testScenarios->isNotEmpty())->count();

        $testCasesTotal = $allTestCases->count();
        $testCasesPassed = $allTestCases->filter(
            fn (TestCase $tc) => $this->latestResultForTestCase($tc) === 'pass'
        )->count();

        return [
            'acs_total'             => $acsTotal,
            'acs_with_test'         => $acsWithTest,
            'acs_with_test_pct'     => $acsTotal > 0 ? round($acsWithTest / $acsTotal * 100, 1) : 0.0,
            'test_cases_total'      => $testCasesTotal,
            'test_cases_passed'     => $testCasesPassed,
            'test_cases_passed_pct' => $testCasesTotal > 0 ? round($testCasesPassed / $testCasesTotal * 100, 1) : 0.0,
        ];
    }

    private function latestResultForTestCase(TestCase $tc): ?string
    {
        return $this->testCaseLatestResults[$tc->id]
            ?? $this->scenarioLatestResults[$tc->test_scenario_id]
            ?? null;
    }
}
