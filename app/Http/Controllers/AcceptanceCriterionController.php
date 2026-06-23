<?php

namespace App\Http\Controllers;

use App\Enums\AcceptanceCriterionStatus;
use App\Enums\RequirementType;
use App\Http\Requests\AcceptanceCriterion\AcceptanceCriterionRequest;
use App\Http\Resources\AcceptanceCriterionResource;
use App\Models\AcceptanceCriterion;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @tags Acceptance Criteria
 */
class AcceptanceCriterionController extends Controller
{
    /**
     * List acceptance criteria for a project.
     *
     * @queryParam requirement_id integer Filter by requirement ID. Example: 3
     * @queryParam status string Filter by status (draft, approved). Example: approved
     * @queryParam supplier_passed boolean Filter by supplier pass result. Example: true
     * @queryParam client_passed boolean Filter by client pass result. Example: true
     *
     * @response {"data": [{"id": 1, "ref": "AC-001", "status": "draft"}]}
     */
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [AcceptanceCriterion::class, $project]);

        $query = $project->acceptanceCriteria()->with(['requirement'])->latest();

        if ($request->filled('requirement_id')) {
            $query->where('requirement_id', $request->requirement_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->boolean('supplier_passed', null) !== null) {
            $query->where('supplier_passed', $request->boolean('supplier_passed'));
        }
        if ($request->boolean('client_passed', null) !== null) {
            $query->where('client_passed', $request->boolean('client_passed'));
        }

        return AcceptanceCriterionResource::collection($query->get());
    }

    /**
     * Create an acceptance criterion.
     *
     * @response 201 {"data": {"id": 1, "ref": "AC-001", "status": "draft"}}
     */
    public function store(AcceptanceCriterionRequest $request, Project $project): AcceptanceCriterionResource
    {
        $this->authorize('create', [AcceptanceCriterion::class, $project]);

        $validated = $request->validated();

        $this->assertRequirementBelongsToProject($project, $validated['requirement_id']);

        $validated['ref']        = AcceptanceCriterion::nextRef($project->id);
        $validated['status']     = AcceptanceCriterionStatus::Draft->value;
        $validated['project_id'] = $project->id;
        $validated['created_by'] = auth()->user()->person_id;

        $criterion = AcceptanceCriterion::create($validated);

        return new AcceptanceCriterionResource($criterion->load(['requirement']));
    }

    /**
     * Get an acceptance criterion.
     *
     * @response {"data": {"id": 1, "ref": "AC-001", "description": "..."}}
     */
    public function show(Project $project, AcceptanceCriterion $acceptanceCriterion): AcceptanceCriterionResource
    {
        $this->authorize('view', [AcceptanceCriterion::class, $project, $acceptanceCriterion]);

        return new AcceptanceCriterionResource($acceptanceCriterion->load(['requirement', 'approvedBy']));
    }

    /**
     * Update an acceptance criterion.
     *
     * @response {"data": {"id": 1, "description": "Updated"}}
     */
    public function update(AcceptanceCriterionRequest $request, Project $project, AcceptanceCriterion $acceptanceCriterion): AcceptanceCriterionResource
    {
        $this->authorize('update', [AcceptanceCriterion::class, $project, $acceptanceCriterion]);

        $acceptanceCriterion->update(array_merge(
            $request->validated(),
            ['updated_by' => auth()->user()->person_id]
        ));

        return new AcceptanceCriterionResource($acceptanceCriterion->fresh()->load(['requirement']));
    }

    /**
     * Delete an acceptance criterion (soft delete).
     *
     * @response 204 {}
     */
    public function destroy(Project $project, AcceptanceCriterion $acceptanceCriterion): Response
    {
        $this->authorize('delete', [AcceptanceCriterion::class, $project, $acceptanceCriterion]);

        $acceptanceCriterion->delete();

        return response()->noContent();
    }

    /**
     * Approve an acceptance criterion (marks it as accepted).
     *
     * @response {"data": {"id": 1, "status": "approved"}}
     * @response 409 {"message": "Acceptance criterion is already approved."}
     */
    public function approve(Project $project, AcceptanceCriterion $acceptanceCriterion): AcceptanceCriterionResource
    {
        $this->authorize('approve', [AcceptanceCriterion::class, $project, $acceptanceCriterion]);

        abort_if(
            $acceptanceCriterion->status === AcceptanceCriterionStatus::Approved,
            409,
            'Acceptance criterion is already approved.'
        );

        $acceptanceCriterion->update([
            'status'      => AcceptanceCriterionStatus::Approved->value,
            'approved_by' => auth()->user()->person_id,
            'approved_at' => now(),
            'updated_by'  => auth()->user()->person_id,
        ]);

        return new AcceptanceCriterionResource($acceptanceCriterion->fresh()->load(['approvedBy']));
    }

    private function assertRequirementBelongsToProject(Project $project, int $requirementId): void
    {
        $requirement = $project->requirements()->find($requirementId);

        abort_if(! $requirement, 422, 'Requirement must belong to this project.');

        abort_if(
            $requirement->type === RequirementType::Epic,
            422,
            'Acceptance criteria cannot be linked to an epic. Link to a classic requirement or user story.'
        );
    }
}
