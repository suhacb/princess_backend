<?php

namespace App\Http\Controllers;

use App\Enums\TestSessionPlanStatus;
use App\Http\Requests\TestSessionPlan\TestSessionPlanRequest;
use App\Http\Resources\TestSessionPlanResource;
use App\Models\Project;
use App\Models\TestSessionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TestSessionPlanController extends Controller
{
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [TestSessionPlan::class, $project]);

        $query = $project->testSessionPlans()->latest();

        if ($request->filled('team_type')) {
            $query->where('team_type', $request->team_type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('planned_date')) {
            $query->whereDate('planned_date', $request->planned_date);
        }

        return TestSessionPlanResource::collection($query->get());
    }

    public function store(TestSessionPlanRequest $request, Project $project): TestSessionPlanResource
    {
        $this->authorize('create', [TestSessionPlan::class, $project]);

        $validated  = $request->validated();
        $scenarioIds = $validated['scenario_ids'] ?? [];
        unset($validated['scenario_ids']);

        $this->assertScenariosValid($project, $scenarioIds);

        $plan = $project->testSessionPlans()->create(array_merge($validated, [
            'ref'        => TestSessionPlan::nextRef($project->id),
            'status'     => TestSessionPlanStatus::Draft->value,
            'created_by' => auth()->user()->person_id,
        ]));

        $this->syncScenariosOrdered($plan, $scenarioIds);

        return new TestSessionPlanResource($plan->load(['scenarios.testCases', 'assignee']));
    }

    public function show(Project $project, TestSessionPlan $testSessionPlan): TestSessionPlanResource
    {
        $this->authorize('view', [TestSessionPlan::class, $project, $testSessionPlan]);

        return new TestSessionPlanResource(
            $testSessionPlan->load(['scenarios.testCases', 'assignee'])
        );
    }

    public function update(TestSessionPlanRequest $request, Project $project, TestSessionPlan $testSessionPlan): TestSessionPlanResource
    {
        $this->authorize('update', [TestSessionPlan::class, $project, $testSessionPlan]);

        $validated   = $request->validated();
        $scenarioIds = $validated['scenario_ids'] ?? null;
        unset($validated['scenario_ids']);

        if ($scenarioIds !== null) {
            $this->assertScenariosValid($project, $scenarioIds);
            $this->syncScenariosOrdered($testSessionPlan, $scenarioIds);
        }

        $testSessionPlan->update(array_merge($validated, [
            'updated_by' => auth()->user()->person_id,
        ]));

        return new TestSessionPlanResource($testSessionPlan->fresh()->load(['scenarios.testCases', 'assignee']));
    }

    public function destroy(Project $project, TestSessionPlan $testSessionPlan): Response
    {
        $this->authorize('delete', [TestSessionPlan::class, $project, $testSessionPlan]);

        $testSessionPlan->delete();

        return response()->noContent();
    }

    public function activate(Project $project, TestSessionPlan $testSessionPlan): TestSessionPlanResource
    {
        $this->authorize('activate', [TestSessionPlan::class, $project, $testSessionPlan]);

        abort_if(
            $testSessionPlan->status !== TestSessionPlanStatus::Draft,
            409,
            'Only draft plans can be activated.'
        );

        $testSessionPlan->update([
            'status'     => TestSessionPlanStatus::Active->value,
            'updated_by' => auth()->user()->person_id,
        ]);

        return new TestSessionPlanResource($testSessionPlan->fresh());
    }

    public function complete(Project $project, TestSessionPlan $testSessionPlan): TestSessionPlanResource
    {
        $this->authorize('complete', [TestSessionPlan::class, $project, $testSessionPlan]);

        abort_if(
            $testSessionPlan->status !== TestSessionPlanStatus::Active,
            409,
            'Only active plans can be completed.'
        );

        $testSessionPlan->update([
            'status'     => TestSessionPlanStatus::Completed->value,
            'updated_by' => auth()->user()->person_id,
        ]);

        return new TestSessionPlanResource($testSessionPlan->fresh());
    }

    public function cancel(Project $project, TestSessionPlan $testSessionPlan): TestSessionPlanResource
    {
        $this->authorize('cancel', [TestSessionPlan::class, $project, $testSessionPlan]);

        abort_if(
            in_array($testSessionPlan->status, [TestSessionPlanStatus::Completed, TestSessionPlanStatus::Cancelled]),
            409,
            'Completed or already cancelled plans cannot be cancelled.'
        );

        $testSessionPlan->update([
            'status'     => TestSessionPlanStatus::Cancelled->value,
            'updated_by' => auth()->user()->person_id,
        ]);

        return new TestSessionPlanResource($testSessionPlan->fresh());
    }

    public function document(Project $project, TestSessionPlan $testSessionPlan): JsonResponse
    {
        $this->authorize('view', [TestSessionPlan::class, $project, $testSessionPlan]);

        $testSessionPlan->load(['scenarios' => fn ($q) => $q->with('testCases'), 'assignee']);

        return response()->json([
            'data' => [
                'ref'          => $testSessionPlan->ref,
                'title'        => $testSessionPlan->title,
                'description'  => $testSessionPlan->description,
                'planned_date' => $testSessionPlan->planned_date,
                'team_type'    => $testSessionPlan->team_type,
                'status'       => $testSessionPlan->status,
                'assignee'     => $testSessionPlan->assignee?->full_name,
                'scenarios'    => $testSessionPlan->scenarios->map(fn ($s) => [
                    'ref'        => $s->ref,
                    'title'      => $s->title,
                    'type'       => $s->type,
                    'is_testable' => $s->is_testable,
                    'preconditions' => $s->preconditions,
                    'test_cases' => $s->testCases->map(fn ($tc) => [
                        'ref'             => $tc->ref,
                        'title'           => $tc->title,
                        'steps'           => $tc->steps,
                        'expected_result' => $tc->expected_result,
                    ]),
                ]),
            ],
        ]);
    }

    private function assertScenariosValid(Project $project, array $scenarioIds): void
    {
        if (empty($scenarioIds)) {
            return;
        }

        $found = $project->testScenarios()
            ->whereIn('id', $scenarioIds)
            ->where('is_testable', true)
            ->count();

        abort_if(
            $found !== count($scenarioIds),
            422,
            'All scenarios must belong to this project and have is_testable = true.'
        );
    }

    private function syncScenariosOrdered(TestSessionPlan $plan, array $scenarioIds): void
    {
        $sync = [];
        foreach ($scenarioIds as $index => $id) {
            $sync[$id] = ['order' => $index];
        }
        $plan->scenarios()->sync($sync);
    }
}
