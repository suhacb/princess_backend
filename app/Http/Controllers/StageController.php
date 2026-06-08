<?php

namespace App\Http\Controllers;

use App\Enums\StageStatus;
use App\Http\Requests\Stage\StageRequest;
use App\Http\Requests\Stage\TransitionStageRequest;
use App\Http\Resources\StageResource;
use App\Models\Project;
use App\Models\Stage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class StageController extends Controller
{
    /**
     * List all stages for a project.
     *
     * @response {"data": [{"id": 1, "name": "Initiation", "status": "planned"}]}
     * @response 404 {"message": "Not found."}
     */
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Stage::class, $project]);

        $stages = $project->stages()->with('createdBy')->get();

        return StageResource::collection($stages);
    }

    /**
     * Create a new stage within a project.
     *
     * @response 201 {"data": {"id": 1, "name": "Initiation", "type": "initiation"}}
     * @response 422 {"message": "The name field is required."}
     */
    public function store(StageRequest $request, Project $project): StageResource
    {
        $this->authorize('create', [Stage::class, $project]);

        $stage = $project->stages()->create(array_merge(
            $request->validated(),
            ['created_by' => auth()->user()->person_id],
        ));

        return new StageResource($stage->load('createdBy'));
    }

    /**
     * Get a stage.
     *
     * @response {"data": {"id": 1, "name": "Initiation", "tolerances": {}}}
     * @response 404 {"message": "Not found."}
     */
    public function show(Project $project, Stage $stage): StageResource
    {
        $this->authorize('view', [Stage::class, $project, $stage]);

        return new StageResource($stage->load(['createdBy', 'updatedBy']));
    }

    /**
     * Update a stage.
     *
     * @response {"data": {"id": 1, "name": "Initiation Updated"}}
     * @response 404 {"message": "Not found."}
     * @response 422 {"message": "The name field is required."}
     */
    public function update(StageRequest $request, Project $project, Stage $stage): StageResource
    {
        $this->authorize('update', [Stage::class, $project, $stage]);

        $stage->update(array_merge(
            $request->validated(),
            ['updated_by' => auth()->user()->person_id],
        ));

        return new StageResource($stage->load(['createdBy', 'updatedBy']));
    }

    /**
     * Delete a stage (soft delete).
     *
     * @response 204 {}
     * @response 404 {"message": "Not found."}
     */
    public function destroy(Project $project, Stage $stage): Response
    {
        $this->authorize('delete', [Stage::class, $project, $stage]);

        $stage->delete();

        return response()->noContent();
    }

    /**
     * Transition a stage to a new status.
     *
     * Only project managers and project board members may transition stages.
     *
     * @response {"data": {"id": 1, "status": "active"}}
     * @response 404 {"message": "Not found."}
     * @response 422 {"message": "The status field is required."}
     */
    public function transition(TransitionStageRequest $request, Project $project, Stage $stage): StageResource
    {
        $this->authorize('transition', [Stage::class, $project, $stage]);

        $stage->update([
            'status'     => StageStatus::from($request->validated('status')),
            'updated_by' => auth()->user()->person_id,
        ]);

        return new StageResource($stage->load(['createdBy', 'updatedBy']));
    }
}
