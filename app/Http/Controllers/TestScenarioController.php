<?php

namespace App\Http\Controllers;

use App\Enums\TestScenarioStatus;
use App\Http\Requests\TestScenario\TestScenarioRequest;
use App\Http\Resources\AcceptanceCriterionResource;
use App\Http\Resources\TestCaseResource;
use App\Http\Resources\TestScenarioResource;
use App\Models\Project;
use App\Models\TestScenario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TestScenarioController extends Controller
{
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [TestScenario::class, $project]);

        $query = $project->testScenarios()->with(['acceptanceCriteria'])->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('is_testable')) {
            $query->where('is_testable', $request->boolean('is_testable'));
        }
        if ($request->filled('acceptance_criterion_id')) {
            $query->whereHas('acceptanceCriteria', function ($q) use ($request) {
                $q->where('acceptance_criteria.id', $request->acceptance_criterion_id);
            });
        }

        return TestScenarioResource::collection($query->get());
    }

    public function store(TestScenarioRequest $request, Project $project): TestScenarioResource
    {
        $this->authorize('create', [TestScenario::class, $project]);

        $validated = $request->validated();
        $acIds     = $validated['acceptance_criterion_ids'] ?? [];
        unset($validated['acceptance_criterion_ids']);

        $this->assertAcIdsBelongToProject($project, $acIds);

        $scenario = $project->testScenarios()->create(array_merge($validated, [
            'ref'         => TestScenario::nextRef($project->id),
            'status'      => TestScenarioStatus::Draft->value,
            'is_testable' => false,
            'created_by'  => auth()->user()->person_id,
        ]));

        if ($acIds) {
            $scenario->acceptanceCriteria()->sync($acIds);
        }

        return new TestScenarioResource($scenario->load(['testCases', 'acceptanceCriteria']));
    }

    public function show(Project $project, TestScenario $testScenario): TestScenarioResource
    {
        $this->authorize('view', [TestScenario::class, $project, $testScenario]);

        return new TestScenarioResource(
            $testScenario->load(['testCases', 'acceptanceCriteria'])
        );
    }

    public function update(TestScenarioRequest $request, Project $project, TestScenario $testScenario): TestScenarioResource
    {
        $this->authorize('update', [TestScenario::class, $project, $testScenario]);

        $validated = $request->validated();
        $acIds     = $validated['acceptance_criterion_ids'] ?? null;
        unset($validated['acceptance_criterion_ids']);

        if ($acIds !== null) {
            $this->assertAcIdsBelongToProject($project, $acIds);
            $testScenario->acceptanceCriteria()->sync($acIds);
        }

        $testScenario->update(array_merge($validated, [
            'updated_by' => auth()->user()->person_id,
        ]));

        return new TestScenarioResource($testScenario->fresh()->load(['testCases', 'acceptanceCriteria']));
    }

    public function destroy(Project $project, TestScenario $testScenario): Response
    {
        $this->authorize('delete', [TestScenario::class, $project, $testScenario]);

        $testScenario->delete();

        return response()->noContent();
    }

    public function ready(Project $project, TestScenario $testScenario): TestScenarioResource
    {
        $this->authorize('ready', [TestScenario::class, $project, $testScenario]);

        abort_if(
            $testScenario->status !== TestScenarioStatus::Draft,
            409,
            'Only draft scenarios can be marked as ready.'
        );

        abort_if(
            $testScenario->testCases()->count() === 0,
            422,
            'A scenario must have at least one test case before it can be marked ready.'
        );

        $testScenario->update([
            'status'     => TestScenarioStatus::Ready->value,
            'updated_by' => auth()->user()->person_id,
        ]);

        return new TestScenarioResource($testScenario->fresh());
    }

    public function obsolete(Project $project, TestScenario $testScenario): TestScenarioResource
    {
        $this->authorize('obsolete', [TestScenario::class, $project, $testScenario]);

        abort_if(
            $testScenario->status !== TestScenarioStatus::Ready,
            409,
            'Only ready scenarios can be marked obsolete.'
        );

        $testScenario->update([
            'status'     => TestScenarioStatus::Obsolete->value,
            'updated_by' => auth()->user()->person_id,
        ]);

        return new TestScenarioResource($testScenario->fresh());
    }

    public function reopen(Project $project, TestScenario $testScenario): TestScenarioResource
    {
        $this->authorize('reopen', [TestScenario::class, $project, $testScenario]);

        abort_if(
            $testScenario->status !== TestScenarioStatus::Obsolete,
            409,
            'Only obsolete scenarios can be reopened.'
        );

        $testScenario->update([
            'status'     => TestScenarioStatus::Draft->value,
            'updated_by' => auth()->user()->person_id,
        ]);

        return new TestScenarioResource($testScenario->fresh());
    }

    public function markTestable(TestScenarioRequest $request, Project $project, TestScenario $testScenario): TestScenarioResource
    {
        $this->authorize('markTestable', [TestScenario::class, $project, $testScenario]);

        $testScenario->update(array_merge($request->validated(), [
            'is_testable' => true,
            'updated_by'  => auth()->user()->person_id,
        ]));

        return new TestScenarioResource($testScenario->fresh());
    }

    public function markNotTestable(Project $project, TestScenario $testScenario): TestScenarioResource
    {
        $this->authorize('markNotTestable', [TestScenario::class, $project, $testScenario]);

        $testScenario->update([
            'is_testable'    => false,
            'testable_notes' => null,
            'updated_by'     => auth()->user()->person_id,
        ]);

        return new TestScenarioResource($testScenario->fresh());
    }

    public function document(Project $project, TestScenario $testScenario): JsonResponse
    {
        $this->authorize('view', [TestScenario::class, $project, $testScenario]);

        $testScenario->load(['testCases', 'acceptanceCriteria.requirement']);

        return response()->json([
            'data' => [
                'ref'            => $testScenario->ref,
                'title'          => $testScenario->title,
                'type'           => $testScenario->type,
                'status'         => $testScenario->status,
                'is_testable'    => $testScenario->is_testable,
                'testable_notes' => $testScenario->testable_notes,
                'description'    => $testScenario->description,
                'preconditions'  => $testScenario->preconditions,
                'test_cases'     => TestCaseResource::collection($testScenario->testCases),
                'acceptance_criteria' => AcceptanceCriterionResource::collection($testScenario->acceptanceCriteria),
            ],
        ]);
    }

    private function assertAcIdsBelongToProject(Project $project, array $acIds): void
    {
        if (empty($acIds)) {
            return;
        }

        $found = $project->acceptanceCriteria()->whereIn('id', $acIds)->count();
        abort_if(
            $found !== count($acIds),
            422,
            'All acceptance criteria must belong to this project.'
        );
    }
}
