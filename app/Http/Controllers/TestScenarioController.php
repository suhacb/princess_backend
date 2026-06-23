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

/**
 * @tags Test Scenarios
 */
class TestScenarioController extends Controller
{
    /**
     * List test scenarios for a project.
     *
     * @queryParam type string Filter by type (functional, non_functional, integration, performance). Example: functional
     * @queryParam status string Filter by status (draft, ready, obsolete). Example: ready
     * @queryParam is_testable boolean Filter by testability flag. Example: true
     * @queryParam acceptance_criterion_id integer Filter by linked acceptance criterion ID. Example: 2
     *
     * @response {"data": [{"id": 1, "ref": "TSC-001", "type": "functional", "status": "draft"}]}
     */
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

    /**
     * Create a test scenario.
     *
     * @response 201 {"data": {"id": 1, "ref": "TSC-001", "status": "draft", "is_testable": false}}
     */
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

    /**
     * Get a test scenario with its test cases and linked acceptance criteria.
     *
     * @response {"data": {"id": 1, "ref": "TSC-001", "test_cases": [], "acceptance_criteria": []}}
     */
    public function show(Project $project, TestScenario $testScenario): TestScenarioResource
    {
        $this->authorize('view', [TestScenario::class, $project, $testScenario]);

        return new TestScenarioResource(
            $testScenario->load(['testCases', 'acceptanceCriteria'])
        );
    }

    /**
     * Update a test scenario.
     *
     * @response {"data": {"id": 1, "title": "Updated"}}
     */
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

    /**
     * Delete a test scenario (soft delete).
     *
     * @response 204 {}
     */
    public function destroy(Project $project, TestScenario $testScenario): Response
    {
        $this->authorize('delete', [TestScenario::class, $project, $testScenario]);

        $testScenario->delete();

        return response()->noContent();
    }

    /**
     * Mark a test scenario as ready for execution (requires at least one test case).
     *
     * @response {"data": {"id": 1, "status": "ready"}}
     * @response 409 {"message": "Only draft scenarios can be marked as ready."}
     */
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

    /**
     * Mark a ready scenario as obsolete.
     *
     * @response {"data": {"id": 1, "status": "obsolete"}}
     * @response 409 {"message": "Only ready scenarios can be marked obsolete."}
     */
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

    /**
     * Reopen an obsolete scenario back to draft.
     *
     * @response {"data": {"id": 1, "status": "draft"}}
     * @response 409 {"message": "Only obsolete scenarios can be reopened."}
     */
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

    /**
     * Mark a scenario as testable (requires testable_notes if applicable).
     *
     * @response {"data": {"id": 1, "is_testable": true}}
     */
    public function markTestable(TestScenarioRequest $request, Project $project, TestScenario $testScenario): TestScenarioResource
    {
        $this->authorize('markTestable', [TestScenario::class, $project, $testScenario]);

        $testScenario->update(array_merge($request->validated(), [
            'is_testable' => true,
            'updated_by'  => auth()->user()->person_id,
        ]));

        return new TestScenarioResource($testScenario->fresh());
    }

    /**
     * Mark a scenario as not testable (clears testable_notes).
     *
     * @response {"data": {"id": 1, "is_testable": false}}
     */
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

    /**
     * Export a test scenario as a structured document with test cases and linked ACs.
     *
     * @response {"data": {"ref": "TSC-001", "title": "...", "test_cases": [], "acceptance_criteria": []}}
     */
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
