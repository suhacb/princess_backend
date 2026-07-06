<?php

namespace App\Http\Controllers;

use App\Enums\ChangeStatus;
use App\Enums\ProjectRole;
use App\Http\Requests\ChangeLog\StoreChangeRequest;
use App\Http\Requests\ChangeLog\UpdateChangeRequest;
use App\Http\Resources\ChangeResource;
use App\Models\Change;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @tags Change Log
 */
class ChangeLogController extends Controller
{
    /**
     * List change requests for a project.
     *
     * @response {"data": [{"id": 1, "title": "...", "status": "proposed"}]}
     */
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Change::class, $project]);

        $changes = $project->changes()->with(['raisedBy', 'decidedBy'])->latest()->get();

        return ChangeResource::collection($changes);
    }

    /**
     * Raise a change request.
     *
     * @response 201 {"data": {"id": 1, "title": "...", "status": "proposed"}}
     */
    public function store(StoreChangeRequest $request, Project $project): ChangeResource
    {
        $this->authorize('create', [Change::class, $project]);

        $change = $project->changes()->create(array_merge(
            $request->validated(),
            [
                'raised_by' => auth()->user()->person_id,
                'raised_at' => now(),
                'status'    => ChangeStatus::Proposed->value,
            ]
        ));

        return new ChangeResource($change->load(['raisedBy', 'decidedBy']));
    }

    /**
     * Get a change request.
     *
     * @response {"data": {"id": 1, "title": "..."}}
     */
    public function show(Project $project, Change $change): ChangeResource
    {
        $this->authorize('view', [Change::class, $project, $change]);

        return new ChangeResource($change->load(['raisedBy', 'decidedBy']));
    }

    /**
     * Update a change request.
     *
     * @response {"data": {"id": 1, "title": "Updated"}}
     */
    public function update(UpdateChangeRequest $request, Project $project, Change $change): ChangeResource
    {
        $this->authorize('update', [Change::class, $project, $change]);

        $change->update($request->validated());

        return new ChangeResource($change->load(['raisedBy', 'decidedBy']));
    }

    /**
     * Delete a change request.
     *
     * @response 204 {}
     */
    public function destroy(Project $project, Change $change): Response
    {
        $this->authorize('delete', [Change::class, $project, $change]);

        $change->delete();

        return response()->noContent();
    }

    /**
     * Approve a change request (Board or Change Authority).
     *
     * @response {"data": {"id": 1, "status": "approved"}}
     * @response 409 {"message": "Change request is not in a decidable state."}
     */
    public function approve(Request $request, Project $project, Change $change): ChangeResource
    {
        $this->authorize('approve', [Change::class, $project, $change]);

        if (!in_array($change->status, [ChangeStatus::Proposed, ChangeStatus::Assessed])) {
            abort(409, 'Change request is not in a decidable state.');
        }

        $validated = $request->validate([
            'decision_rationale' => ['nullable', 'string'],
        ]);

        $change->update([
            'status'             => ChangeStatus::Approved->value,
            'decision_by'        => auth()->user()->person_id,
            'decision_at'        => now(),
            'decision_rationale' => $validated['decision_rationale'] ?? null,
        ]);

        return new ChangeResource($change->load(['raisedBy', 'decidedBy']));
    }

    /**
     * Reject a change request (Board or Change Authority).
     *
     * @response {"data": {"id": 1, "status": "rejected"}}
     * @response 409 {"message": "Change request is not in a decidable state."}
     */
    public function reject(Request $request, Project $project, Change $change): ChangeResource
    {
        $this->authorize('approve', [Change::class, $project, $change]);

        if (!in_array($change->status, [ChangeStatus::Proposed, ChangeStatus::Assessed])) {
            abort(409, 'Change request is not in a decidable state.');
        }

        $validated = $request->validate([
            'decision_rationale' => ['nullable', 'string'],
        ]);

        $change->update([
            'status'             => ChangeStatus::Rejected->value,
            'decision_by'        => auth()->user()->person_id,
            'decision_at'        => now(),
            'decision_rationale' => $validated['decision_rationale'] ?? null,
        ]);

        return new ChangeResource($change->load(['raisedBy', 'decidedBy']));
    }
}
