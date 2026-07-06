<?php

namespace App\Http\Controllers;

use App\Enums\BoundaryStatus;
use App\Http\Requests\StageBoundary\StoreStageBoundaryRequest;
use App\Http\Requests\StageBoundary\UpdateStageBoundaryRequest;
use App\Http\Resources\StageBoundaryResource;
use App\Models\Project;
use App\Models\Stage;
use App\Models\StageBoundary;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @tags Stage Boundaries
 */
class StageBoundaryController extends Controller
{
    /**
     * List all boundaries for a stage.
     *
     * @response {"data": [{"id": 1, "type": "end_stage_report", "status": "draft"}]}
     * @response 404 {"message": "Not found."}
     */
    public function index(Project $project, Stage $stage): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [StageBoundary::class, $project]);

        $boundaries = $stage->boundaries()->with(['createdBy', 'submittedBy', 'approvedBy'])->get();

        return StageBoundaryResource::collection($boundaries);
    }

    /**
     * Create a boundary for a stage.
     *
     * @response 201 {"data": {"id": 1, "type": "end_stage_report", "status": "draft"}}
     * @response 422 {"message": "The type field is required."}
     */
    public function store(StoreStageBoundaryRequest $request, Project $project, Stage $stage): StageBoundaryResource
    {
        $this->authorize('create', [StageBoundary::class, $project]);

        $boundary = $stage->boundaries()->create(array_merge(
            $request->validated(),
            ['created_by' => auth()->user()->person_id],
        ));

        return new StageBoundaryResource($boundary->load('createdBy'));
    }

    /**
     * Get a boundary.
     *
     * @response {"data": {"id": 1, "type": "end_stage_report", "status": "draft"}}
     * @response 404 {"message": "Not found."}
     */
    public function show(Project $project, Stage $stage, StageBoundary $boundary): StageBoundaryResource
    {
        $this->authorize('view', [StageBoundary::class, $project, $stage, $boundary]);

        return new StageBoundaryResource(
            $boundary->load(['createdBy', 'updatedBy', 'submittedBy', 'approvedBy'])
        );
    }

    /**
     * Update a boundary (only while in draft status).
     *
     * @response {"data": {"id": 1, "title": "Updated title"}}
     * @response 404 {"message": "Not found."}
     * @response 409 {"error": "Only draft boundaries can be edited."}
     */
    public function update(UpdateStageBoundaryRequest $request, Project $project, Stage $stage, StageBoundary $boundary): StageBoundaryResource
    {
        $this->authorize('update', [StageBoundary::class, $project, $stage, $boundary]);

        abort_if(
            $boundary->status !== BoundaryStatus::Draft,
            409,
            'Only draft boundaries can be edited.'
        );

        $boundary->update(array_merge(
            $request->validated(),
            ['updated_by' => auth()->user()->person_id],
        ));

        return new StageBoundaryResource($boundary->load(['createdBy', 'updatedBy']));
    }

    /**
     * Delete a boundary (only while in draft status).
     *
     * @response 204 {}
     * @response 404 {"message": "Not found."}
     * @response 409 {"error": "Only draft boundaries can be deleted."}
     */
    public function destroy(Project $project, Stage $stage, StageBoundary $boundary): Response
    {
        $this->authorize('delete', [StageBoundary::class, $project, $stage, $boundary]);

        abort_if(
            $boundary->status !== BoundaryStatus::Draft,
            409,
            'Only draft boundaries can be deleted.'
        );

        $boundary->delete();

        return response()->noContent();
    }

    /**
     * Submit a boundary for approval.
     *
     * @response {"data": {"id": 1, "status": "submitted"}}
     * @response 409 {"error": "Only draft boundaries can be submitted."}
     */
    public function submit(Project $project, Stage $stage, StageBoundary $boundary): StageBoundaryResource
    {
        $this->authorize('submit', [StageBoundary::class, $project, $stage, $boundary]);

        abort_if(
            $boundary->status !== BoundaryStatus::Draft,
            409,
            'Only draft boundaries can be submitted.'
        );

        $boundary->update([
            'status'       => BoundaryStatus::Submitted,
            'submitted_at' => now(),
            'submitted_by' => auth()->user()->person_id,
            'updated_by'   => auth()->user()->person_id,
        ]);

        return new StageBoundaryResource($boundary->load(['submittedBy', 'createdBy']));
    }

    /**
     * Approve a submitted boundary.
     *
     * Only project board members may approve boundaries.
     *
     * @response {"data": {"id": 1, "status": "approved"}}
     * @response 409 {"error": "Only submitted boundaries can be approved."}
     */
    public function approve(Project $project, Stage $stage, StageBoundary $boundary): StageBoundaryResource
    {
        $this->authorize('approveReject', [StageBoundary::class, $project, $stage, $boundary]);

        abort_if(
            $boundary->status !== BoundaryStatus::Submitted,
            409,
            'Only submitted boundaries can be approved.'
        );

        $boundary->update([
            'status'      => BoundaryStatus::Approved,
            'approved_at' => now(),
            'approved_by' => auth()->user()->person_id,
            'updated_by'  => auth()->user()->person_id,
        ]);

        return new StageBoundaryResource($boundary->load(['approvedBy', 'createdBy']));
    }

    /**
     * Reject a submitted boundary.
     *
     * Only project board members may reject boundaries.
     *
     * @response {"data": {"id": 1, "status": "rejected"}}
     * @response 409 {"error": "Only submitted boundaries can be rejected."}
     */
    public function reject(Project $project, Stage $stage, StageBoundary $boundary): StageBoundaryResource
    {
        $this->authorize('approveReject', [StageBoundary::class, $project, $stage, $boundary]);

        abort_if(
            $boundary->status !== BoundaryStatus::Submitted,
            409,
            'Only submitted boundaries can be rejected.'
        );

        $boundary->update([
            'status'     => BoundaryStatus::Rejected,
            'updated_by' => auth()->user()->person_id,
        ]);

        return new StageBoundaryResource($boundary->load(['createdBy', 'updatedBy']));
    }
}
