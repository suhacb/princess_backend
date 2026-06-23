<?php

namespace App\Http\Controllers;

use App\Enums\RequirementStatus;
use App\Enums\RequirementType;
use App\Http\Requests\Requirement\RequirementRequest;
use App\Http\Resources\RequirementResource;
use App\Models\Project;
use App\Models\Requirement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @tags Requirements
 */
class RequirementController extends Controller
{
    /**
     * List requirements for a project.
     *
     * @queryParam type string Filter by type (epic, classic, user_story). Example: classic
     * @queryParam priority string Filter by priority (must, should, could, wont). Example: must
     * @queryParam status string Filter by status (draft, reviewed, approved, rejected, deferred). Example: approved
     * @queryParam owner_id integer Filter by owner person ID. Example: 1
     * @queryParam parent_id integer Filter by parent epic ID. Example: 5
     *
     * @response {"data": [{"id": 1, "ref": "REQ-001", "type": "classic", "status": "draft"}]}
     */
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Requirement::class, $project]);

        $query = $project->requirements()->with(['owner'])->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('owner_id')) {
            $query->where('owner_id', $request->owner_id);
        }
        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        return RequirementResource::collection($query->get());
    }

    /**
     * Create a requirement.
     *
     * @response 201 {"data": {"id": 1, "ref": "REQ-001", "type": "classic", "status": "draft"}}
     * @response 422 {"message": "The title field is required."}
     */
    public function store(RequirementRequest $request, Project $project): RequirementResource
    {
        $this->authorize('create', [Requirement::class, $project]);

        $validated = $request->validated();
        $type      = RequirementType::from($validated['type']);

        $this->assertParentValid($project, $validated);
        $this->assertNoUserStoryFieldsOnNonUserStory($type, $validated);

        $validated['ref']        = Requirement::nextRef($project->id, $type);
        $validated['status']     = RequirementStatus::Draft->value;
        $validated['version']    = 1;
        $validated['created_by'] = auth()->user()->person_id;

        $requirement = $project->requirements()->create($validated);

        return new RequirementResource($requirement->load(['owner']));
    }

    /**
     * Get a requirement with its children and acceptance criteria.
     *
     * @response {"data": {"id": 1, "ref": "REQ-001", "acceptance_criteria": []}}
     */
    public function show(Project $project, Requirement $requirement): RequirementResource
    {
        $this->authorize('view', [Requirement::class, $project, $requirement]);

        $requirement->load(['owner', 'approvedBy', 'children', 'acceptanceCriteria', 'parent']);

        return new RequirementResource($requirement);
    }

    /**
     * Update a requirement (bumps version).
     *
     * @response {"data": {"id": 1, "ref": "REQ-001", "version": 2}}
     */
    public function update(RequirementRequest $request, Project $project, Requirement $requirement): RequirementResource
    {
        $this->authorize('update', [Requirement::class, $project, $requirement]);

        $validated = $request->validated();

        if (isset($validated['parent_id'])) {
            $this->assertParentValid($project, $validated);
        }

        $requirement->update(array_merge($validated, [
            'version'    => $requirement->version + 1,
            'updated_by' => auth()->user()->person_id,
        ]));

        return new RequirementResource($requirement->fresh()->load(['owner']));
    }

    /**
     * Delete a requirement (soft delete).
     *
     * @response 204 {}
     */
    public function destroy(Project $project, Requirement $requirement): Response
    {
        $this->authorize('delete', [Requirement::class, $project, $requirement]);

        $requirement->delete();

        return response()->noContent();
    }

    /**
     * Move a requirement to reviewed status.
     *
     * @response {"data": {"id": 1, "status": "reviewed"}}
     * @response 409 {"message": "Only draft requirements can be sent for review."}
     */
    public function review(Project $project, Requirement $requirement): RequirementResource
    {
        $this->authorize('review', [Requirement::class, $project, $requirement]);

        abort_if(
            $requirement->status !== RequirementStatus::Draft,
            409,
            'Only draft requirements can be sent for review.'
        );

        $requirement->update([
            'status'     => RequirementStatus::Reviewed->value,
            'updated_by' => auth()->user()->person_id,
        ]);

        return new RequirementResource($requirement->fresh());
    }

    /**
     * Approve a reviewed requirement.
     *
     * @response {"data": {"id": 1, "status": "approved"}}
     * @response 409 {"message": "Only reviewed requirements can be approved."}
     */
    public function approve(Project $project, Requirement $requirement): RequirementResource
    {
        $this->authorize('approve', [Requirement::class, $project, $requirement]);

        abort_if(
            $requirement->status !== RequirementStatus::Reviewed,
            409,
            'Only reviewed requirements can be approved.'
        );

        $requirement->update([
            'status'      => RequirementStatus::Approved->value,
            'approved_by' => auth()->user()->person_id,
            'approved_at' => now(),
            'updated_by'  => auth()->user()->person_id,
        ]);

        return new RequirementResource($requirement->fresh()->load(['approvedBy']));
    }

    /**
     * Reject a reviewed requirement.
     *
     * @response {"data": {"id": 1, "status": "rejected"}}
     * @response 409 {"message": "Only reviewed requirements can be rejected."}
     */
    public function reject(Project $project, Requirement $requirement): RequirementResource
    {
        $this->authorize('reject', [Requirement::class, $project, $requirement]);

        abort_if(
            $requirement->status !== RequirementStatus::Reviewed,
            409,
            'Only reviewed requirements can be rejected.'
        );

        $requirement->update([
            'status'     => RequirementStatus::Rejected->value,
            'updated_by' => auth()->user()->person_id,
        ]);

        return new RequirementResource($requirement->fresh());
    }

    /**
     * Defer a requirement to a later stage.
     *
     * @response {"data": {"id": 1, "status": "deferred"}}
     */
    public function defer(Project $project, Requirement $requirement): RequirementResource
    {
        $this->authorize('defer', [Requirement::class, $project, $requirement]);

        $requirement->update([
            'status'     => RequirementStatus::Deferred->value,
            'updated_by' => auth()->user()->person_id,
        ]);

        return new RequirementResource($requirement->fresh());
    }

    private function assertParentValid(Project $project, array $validated): void
    {
        if (empty($validated['parent_id'])) {
            return;
        }

        $parent = $project->requirements()
            ->where('id', $validated['parent_id'])
            ->where('type', RequirementType::Epic->value)
            ->first();

        abort_if(! $parent, 422, 'Parent must be an epic belonging to this project.');
    }

    private function assertNoUserStoryFieldsOnNonUserStory(RequirementType $type, array $validated): void
    {
        if ($type !== RequirementType::UserStory) {
            abort_if(
                ! empty($validated['role']) || ! empty($validated['action']) || ! empty($validated['benefit']),
                422,
                'Role, action, and benefit fields are only valid for user stories.'
            );
        }

        if ($type === RequirementType::Epic) {
            abort_if(
                ! empty($validated['parent_id']),
                422,
                'Epics cannot have a parent.'
            );
        }
    }
}
