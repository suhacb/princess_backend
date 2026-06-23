<?php

namespace App\Http\Controllers;

use App\Enums\TeamType;
use App\Enums\TestResultStatus;
use App\Enums\TestSessionStatus;
use App\Http\Requests\TestSession\TestSessionRequest;
use App\Http\Resources\TestSessionResource;
use App\Http\Resources\TestSessionResultResource;
use App\Models\Project;
use App\Models\TestScenario;
use App\Models\TestSession;
use App\Models\TestSessionResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * @tags Test Sessions
 */
class TestSessionController extends Controller
{
    /**
     * List test sessions for a project.
     *
     * @queryParam team_type string Filter by team type (supplier, client). Example: supplier
     * @queryParam status string Filter by status (planned, in_progress, completed, cancelled). Example: completed
     * @queryParam tester_id integer Filter by tester person ID. Example: 5
     * @queryParam test_session_plan_id integer Filter by plan ID. Example: 2
     *
     * @response {"data": [{"id": 1, "ref": "TS-001", "status": "planned"}]}
     */
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [TestSession::class, $project]);

        $query = $project->testSessions()->with(['tester'])->latest();

        if ($request->filled('team_type')) {
            $query->where('team_type', $request->team_type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('tester_id')) {
            $query->where('tester_id', $request->tester_id);
        }
        if ($request->filled('test_session_plan_id')) {
            $query->where('test_session_plan_id', $request->test_session_plan_id);
        }

        return TestSessionResource::collection($query->get());
    }

    /**
     * Create a test session. Pre-populates results from the linked plan's scenarios.
     *
     * @response 201 {"data": {"id": 1, "ref": "TS-001", "status": "planned", "results": []}}
     */
    public function store(TestSessionRequest $request, Project $project): TestSessionResource
    {
        $this->authorize('create', [TestSession::class, $project]);

        $validated = $request->validated();
        $planId    = $validated['test_session_plan_id'] ?? null;

        $this->assertPlanValid($project, $planId, $validated['team_type'] ?? null);
        $this->assertTesterIsMember($project, $validated['tester_id']);

        $session = $project->testSessions()->create(array_merge($validated, [
            'ref'        => TestSession::nextRef($project->id),
            'status'     => TestSessionStatus::Planned->value,
            'created_by' => auth()->user()->person_id,
        ]));

        // Pre-populate results for plan scenarios
        if ($planId) {
            $plan = $project->testSessionPlans()->find($planId);
            foreach ($plan->scenarios()->orderByPivot('order')->get() as $scenario) {
                TestSessionResult::create([
                    'test_session_id'  => $session->id,
                    'test_scenario_id' => $scenario->id,
                    'result'           => TestResultStatus::NotRun->value,
                ]);
            }
        }

        return new TestSessionResource($session->load(['results.testScenario', 'tester']));
    }

    /**
     * Get a test session with its results and linked scenarios.
     *
     * @response {"data": {"id": 1, "ref": "TS-001", "results": []}}
     */
    public function show(Project $project, TestSession $testSession): TestSessionResource
    {
        $this->authorize('view', [TestSession::class, $project, $testSession]);

        return new TestSessionResource(
            $testSession->load(['results.testScenario', 'tester', 'plan'])
        );
    }

    /**
     * Update a test session.
     *
     * @response {"data": {"id": 1, "title": "Updated"}}
     */
    public function update(TestSessionRequest $request, Project $project, TestSession $testSession): TestSessionResource
    {
        $this->authorize('update', [TestSession::class, $project, $testSession]);

        $validated = $request->validated();

        if (isset($validated['tester_id'])) {
            $this->assertTesterIsMember($project, $validated['tester_id']);
        }

        $testSession->update(array_merge($validated, [
            'updated_by' => auth()->user()->person_id,
        ]));

        return new TestSessionResource($testSession->fresh()->load(['results.testScenario', 'tester']));
    }

    /**
     * Delete a test session (soft delete).
     *
     * @response 204 {}
     */
    public function destroy(Project $project, TestSession $testSession): Response
    {
        $this->authorize('delete', [TestSession::class, $project, $testSession]);

        $testSession->delete();

        return response()->noContent();
    }

    /**
     * Start a planned test session.
     *
     * @response {"data": {"id": 1, "status": "in_progress"}}
     * @response 409 {"message": "Only planned sessions can be started."}
     */
    public function start(Project $project, TestSession $testSession): TestSessionResource
    {
        $this->authorize('start', [TestSession::class, $project, $testSession]);

        abort_if(
            $testSession->status !== TestSessionStatus::Planned,
            409,
            'Only planned sessions can be started.'
        );

        $testSession->update([
            'status'     => TestSessionStatus::InProgress->value,
            'updated_by' => auth()->user()->person_id,
        ]);

        return new TestSessionResource($testSession->fresh());
    }

    /**
     * Complete an in-progress test session. Recomputes AC pass/fail statuses and raises issues for failures.
     *
     * @response {"data": {"id": 1, "status": "completed"}}
     * @response 409 {"message": "Only in-progress sessions can be completed."}
     */
    public function complete(Project $project, TestSession $testSession): TestSessionResource
    {
        $this->authorize('complete', [TestSession::class, $project, $testSession]);

        abort_if(
            $testSession->status !== TestSessionStatus::InProgress,
            409,
            'Only in-progress sessions can be completed.'
        );

        DB::transaction(function () use ($testSession) {
            $testSession->update([
                'status'     => TestSessionStatus::Completed->value,
                'updated_by' => auth()->user()->person_id,
            ]);

            $testSession->recomputeAcStatus();
            $testSession->createIssuesForFailures();
        });

        return new TestSessionResource($testSession->fresh()->load(['results.testScenario']));
    }

    /**
     * Cancel a test session.
     *
     * @response {"data": {"id": 1, "status": "cancelled"}}
     * @response 409 {"message": "Completed or already cancelled sessions cannot be cancelled."}
     */
    public function cancel(Project $project, TestSession $testSession): TestSessionResource
    {
        $this->authorize('cancel', [TestSession::class, $project, $testSession]);

        abort_if(
            in_array($testSession->status, [TestSessionStatus::Completed, TestSessionStatus::Cancelled]),
            409,
            'Completed or already cancelled sessions cannot be cancelled.'
        );

        $testSession->update([
            'status'     => TestSessionStatus::Cancelled->value,
            'updated_by' => auth()->user()->person_id,
        ]);

        return new TestSessionResource($testSession->fresh());
    }

    /**
     * Record or update the result for a scenario within a test session.
     *
     * @response {"data": {"result": "pass", "executed_at": "2026-07-01T10:00:00Z"}}
     * @response 422 {"message": "This scenario is not part of the session."}
     */
    public function updateResult(TestSessionRequest $request, Project $project, TestSession $testSession, TestScenario $testScenario): TestSessionResultResource
    {
        $this->authorize('updateResult', [TestSession::class, $project, $testSession]);

        $result = $testSession->results()->where('test_scenario_id', $testScenario->id)->first();

        abort_if(! $result, 422, 'This scenario is not part of the session.');

        $validated = $request->validated();

        $result->update(array_merge($validated, [
            'executed_at' => now(),
        ]));

        return new TestSessionResultResource($result->fresh()->load('testScenario'));
    }

    /**
     * Export a test session report with pass/fail summary and per-scenario results.
     *
     * @response {"data": {"ref": "TS-001", "summary": {"pass": 10, "fail": 2, "blocked": 1, "not_run": 0}, "results": []}}
     */
    public function report(Project $project, TestSession $testSession): JsonResponse
    {
        $this->authorize('view', [TestSession::class, $project, $testSession]);

        $testSession->load(['results.testScenario', 'tester', 'plan']);

        $counts = [
            'pass'    => $testSession->results->where('result.value', 'pass')->count(),
            'fail'    => $testSession->results->where('result.value', 'fail')->count(),
            'blocked' => $testSession->results->where('result.value', 'blocked')->count(),
            'not_run' => $testSession->results->where('result.value', 'not_run')->count(),
        ];

        return response()->json([
            'data' => [
                'ref'          => $testSession->ref,
                'title'        => $testSession->title,
                'session_date' => $testSession->session_date,
                'team_type'    => $testSession->team_type,
                'environment'  => $testSession->environment,
                'status'       => $testSession->status,
                'notes'        => $testSession->notes,
                'summary'      => $counts,
                'results'      => $testSession->results->map(fn ($r) => [
                    'scenario_ref'   => $r->testScenario->ref,
                    'scenario_title' => $r->testScenario->title,
                    'result'         => $r->result,
                    'notes'          => $r->notes,
                    'defect_ref'     => $r->defect_ref,
                    'executed_at'    => $r->executed_at,
                ]),
            ],
        ]);
    }

    private function assertPlanValid(Project $project, ?int $planId, ?string $teamType): void
    {
        if (! $planId) {
            return;
        }

        $plan = $project->testSessionPlans()->find($planId);
        abort_if(! $plan, 422, 'The test session plan must belong to this project.');

        if ($teamType && $plan->team_type->value !== $teamType) {
            abort(422, 'The session team_type must match the plan team_type.');
        }
    }

    private function assertTesterIsMember(Project $project, int $testerId): void
    {
        $isMember = $project->members()->where('person_id', $testerId)->exists();
        abort_if(! $isMember, 422, 'The tester must be a member of this project.');
    }
}
