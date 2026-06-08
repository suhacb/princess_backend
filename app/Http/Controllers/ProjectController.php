<?php

namespace App\Http\Controllers;

use App\Http\Requests\Project\ProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\Stage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    /**
     * List all projects.
     *
     * @response {"data": [{"id": 1, "name": "Core Banking", "status": "initiation"}], "meta": {"total": 1}}
     */
    public function index(): AnonymousResourceCollection
    {
        $projects = Project::with('createdBy')->withCount('stages')->paginate(20);

        return ProjectResource::collection($projects);
    }

    /**
     * Create a new project.
     *
     * @response 201 {"data": {"id": 1, "name": "Core Banking", "status": "pre_project"}}
     * @response 422 {"message": "The name field is required."}
     */
    public function store(ProjectRequest $request): ProjectResource
    {
        $project = Project::create(array_merge(
            $request->validated(),
            ['created_by' => auth()->user()->person_id],
        ));

        return new ProjectResource($project->load(['createdBy']));
    }

    /**
     * Get a project with its stages.
     *
     * @response {"data": {"id": 1, "name": "Core Banking", "stages": []}}
     * @response 404 {"message": "Not found."}
     */
    public function show(Project $project): ProjectResource
    {
        return new ProjectResource(
            $project->load(['currentStage', 'stages', 'createdBy', 'updatedBy'])
        );
    }

    /**
     * Update a project.
     *
     * @response {"data": {"id": 1, "name": "Core Banking Updated"}}
     * @response 404 {"message": "Not found."}
     * @response 422 {"message": "The name field is required."}
     */
    public function update(ProjectRequest $request, Project $project): ProjectResource
    {
        $project->update(array_merge(
            $request->validated(),
            ['updated_by' => auth()->user()->person_id],
        ));

        return new ProjectResource($project->load(['currentStage', 'createdBy', 'updatedBy']));
    }

    /**
     * Delete a project (soft delete).
     *
     * @response 204 {}
     * @response 404 {"message": "Not found."}
     */
    public function destroy(Project $project): Response
    {
        $project->delete();

        return response()->noContent();
    }

    /**
     * Set the current active stage of a project.
     *
     * @response {"data": {"id": 1, "current_stage": {"id": 2, "name": "Delivery"}}}
     * @response 404 {"message": "Not found."}
     * @response 422 {"message": "The stage_id field is required."}
     */
    public function setCurrentStage(Request $request, Project $project): ProjectResource
    {
        $validated = $request->validate([
            'stage_id' => ['required', 'integer'],
        ]);

        $stage = Stage::where('id', $validated['stage_id'])
            ->where('project_id', $project->id)
            ->firstOrFail();

        $project->update([
            'current_stage_id' => $stage->id,
            'updated_by'       => auth()->user()->person_id,
        ]);

        return new ProjectResource($project->load(['currentStage', 'createdBy', 'updatedBy']));
    }
}
