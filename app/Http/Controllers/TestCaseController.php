<?php

namespace App\Http\Controllers;

use App\Http\Requests\TestCase\TestCaseRequest;
use App\Http\Resources\TestCaseResource;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestScenario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TestCaseController extends Controller
{
    public function index(Request $request, Project $project, TestScenario $testScenario): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [TestCase::class, $project, $testScenario]);

        return TestCaseResource::collection(
            $testScenario->testCases()->get()
        );
    }

    public function store(TestCaseRequest $request, Project $project, TestScenario $testScenario): TestCaseResource
    {
        $this->authorize('create', [TestCase::class, $project, $testScenario]);

        $validated = $request->validated();

        $testCase = $testScenario->testCases()->create(array_merge($validated, [
            'project_id' => $project->id,
            'ref'        => TestCase::nextRef($project->id),
            'created_by' => auth()->user()->person_id,
        ]));

        return new TestCaseResource($testCase);
    }

    public function show(Project $project, TestScenario $testScenario, TestCase $testCase): TestCaseResource
    {
        $this->authorize('view', [TestCase::class, $project, $testScenario, $testCase]);

        return new TestCaseResource($testCase);
    }

    public function update(TestCaseRequest $request, Project $project, TestScenario $testScenario, TestCase $testCase): TestCaseResource
    {
        $this->authorize('update', [TestCase::class, $project, $testScenario, $testCase]);

        $testCase->update(array_merge($request->validated(), [
            'updated_by' => auth()->user()->person_id,
        ]));

        return new TestCaseResource($testCase->fresh());
    }

    public function destroy(Project $project, TestScenario $testScenario, TestCase $testCase): Response
    {
        $this->authorize('delete', [TestCase::class, $project, $testScenario, $testCase]);

        $testCase->delete();

        return response()->noContent();
    }
}
