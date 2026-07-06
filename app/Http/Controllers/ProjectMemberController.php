<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Http\Requests\ProjectMember\StoreProjectMemberRequest;
use App\Http\Requests\ProjectMember\UpdateProjectMemberRequest;
use App\Http\Resources\ProjectMemberResource;
use App\Models\Project;
use App\Models\ProjectMember;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @tags Project Members
 */
class ProjectMemberController extends Controller
{
    /**
     * List all members of a project.
     *
     * @response {"data": [{"id": 1, "role": "project_manager", "side": "customer", "person": {}}]}
     */
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [ProjectMember::class, $project]);

        return ProjectMemberResource::collection(
            $project->members()->with('person')->get()
        );
    }

    /**
     * Add a person to a project with a role.
     *
     * @response 201 {"data": {"id": 1, "role": "team_member", "person": {}}}
     * @response 422 {"message": "The person id has already been taken."}
     */
    public function store(StoreProjectMemberRequest $request, Project $project): ProjectMemberResource
    {
        $this->authorize('create', [ProjectMember::class, $project]);

        $member = $project->members()->create($request->validated());

        return new ProjectMemberResource($member->load('person'));
    }

    /**
     * Update a project member's role or side.
     *
     * @response {"data": {"id": 1, "role": "senior_user"}}
     * @response 422 {"message": "Cannot remove the last project manager."}
     */
    public function update(UpdateProjectMemberRequest $request, Project $project, ProjectMember $member): ProjectMemberResource
    {
        $this->authorize('update', [ProjectMember::class, $project, $member]);

        $validated = $request->validated();

        if (isset($validated['role']) && $validated['role'] !== ProjectRole::ProjectManager->value) {
            $this->ensureNotLastProjectManager($project, $member);
        }

        $member->update($validated);

        return new ProjectMemberResource($member->load('person'));
    }

    /**
     * Remove a member from a project.
     *
     * @response 204 {}
     * @response 422 {"message": "Cannot remove the last project manager."}
     */
    public function destroy(Project $project, ProjectMember $member): Response
    {
        $this->authorize('delete', [ProjectMember::class, $project, $member]);

        if ($member->role === ProjectRole::ProjectManager) {
            $this->ensureNotLastProjectManager($project, $member);
        }

        $member->delete();

        return response()->noContent();
    }

    private function ensureNotLastProjectManager(Project $project, ProjectMember $member): void
    {
        $pmCount = $project->members()
            ->where('role', ProjectRole::ProjectManager->value)
            ->where('id', '!=', $member->id)
            ->count();

        if ($pmCount === 0) {
            abort(422, 'Cannot remove the last project manager.');
        }
    }
}
