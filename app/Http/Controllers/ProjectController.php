<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Http\Requests\Project\ProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\Stage;
use App\Services\Document\ProjectStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @tags Projects
 */
class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectStorageService $projectStorage,
    ) {}

    /**
     * List all projects.
     *
     * @response {"data": [{"id": 1, "name": "Core Banking", "status": "initiation"}], "meta": {"total": 1}}
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Project::class);

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
        $this->authorize('create', Project::class);

        $personId = auth()->user()->person_id;

        $project = Project::create(array_merge(
            $request->validated(),
            ['created_by' => $personId],
        ));

        $project->members()->create([
            'person_id' => $personId,
            'role'      => ProjectRole::ProjectManager->value,
        ]);

        try {
            $this->projectStorage->provision($project);
        } catch (\Throwable) {
            $project->forceDelete();
            abort(503, 'Storage service unavailable. Project creation failed.');
        }

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
        $this->authorize('view', $project);

        return new ProjectResource(
            $project->load(['document', 'currentStage', 'stages', 'createdBy', 'updatedBy'])
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
        $this->authorize('update', $project);

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
        $this->authorize('delete', $project);

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
        $this->authorize('setCurrentStage', $project);

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
