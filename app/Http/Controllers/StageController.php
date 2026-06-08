<?php

namespace App\Http\Controllers;

use App\Enums\StageStatus;
use App\Http\Requests\Stage\StoreStageRequest;
use App\Http\Requests\Stage\TransitionStageRequest;
use App\Http\Requests\Stage\UpdateStageRequest;
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
        $stages = $project->stages()->with('createdBy')->get();

        return StageResource::collection($stages);
    }

    /**
     * Create a new stage within a project.
     *
     * @response 201 {"data": {"id": 1, "name": "Initiation", "type": "initiation"}}
     * @response 422 {"message": "The name field is required."}
     */
    public function store(StoreStageRequest $request, Project $project): StageResource
    {
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
        return new StageResource($stage->load(['createdBy', 'updatedBy']));
    }

    /**
     * Update a stage.
     *
     * @response {"data": {"id": 1, "name": "Initiation Updated"}}
     * @response 404 {"message": "Not found."}
     * @response 422 {"message": "The name field is required."}
     */
    public function update(UpdateStageRequest $request, Project $project, Stage $stage): StageResource
    {
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
        $stage->update([
            'status'     => StageStatus::from($request->validated('status')),
            'updated_by' => auth()->user()->person_id,
        ]);

        return new StageResource($stage->load(['createdBy', 'updatedBy']));
    }
}
