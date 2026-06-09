<?php

namespace App\Http\Controllers;

use App\Enums\IssueStatus;
use App\Http\Requests\IssueLog\IssueLogRequest;
use App\Http\Resources\IssueResource;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class IssueLogController extends Controller
{
    /**
     * List issues for a project.
     *
     * @response {"data": [{"id": 1, "title": "...", "status": "open"}]}
     */
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Issue::class, $project]);

        $issues = $project->issues()->with(['raisedBy', 'assignedTo'])->latest()->get();

        return IssueResource::collection($issues);
    }

    /**
     * Raise a new issue.
     *
     * @response 201 {"data": {"id": 1, "title": "...", "status": "open"}}
     */
    public function store(IssueLogRequest $request, Project $project): IssueResource
    {
        $this->authorize('create', [Issue::class, $project]);

        $issue = $project->issues()->create(array_merge(
            $request->validated(),
            [
                'raised_by' => auth()->user()->person_id,
                'raised_at' => now(),
                'status'    => IssueStatus::Open->value,
            ]
        ));

        return new IssueResource($issue->load(['raisedBy', 'assignedTo']));
    }

    /**
     * Get an issue.
     *
     * @response {"data": {"id": 1, "title": "...", "status": "open"}}
     */
    public function show(Project $project, Issue $issue): IssueResource
    {
        $this->authorize('view', [Issue::class, $project, $issue]);

        return new IssueResource($issue->load(['raisedBy', 'assignedTo']));
    }

    /**
     * Update an issue.
     *
     * @response {"data": {"id": 1, "title": "Updated"}}
     */
    public function update(IssueLogRequest $request, Project $project, Issue $issue): IssueResource
    {
        $this->authorize('update', [Issue::class, $project, $issue]);

        $issue->update($request->validated());

        return new IssueResource($issue->load(['raisedBy', 'assignedTo']));
    }

    /**
     * Delete an issue.
     *
     * @response 204 {}
     */
    public function destroy(Project $project, Issue $issue): Response
    {
        $this->authorize('delete', [Issue::class, $project, $issue]);

        $issue->delete();

        return response()->noContent();
    }

    /**
     * Escalate an issue to the project board.
     *
     * @response {"data": {"id": 1, "status": "escalated"}}
     * @response 409 {"message": "Issue is not open or under review."}
     */
    public function escalate(Request $request, Project $project, Issue $issue): IssueResource
    {
        $this->authorize('escalate', [Issue::class, $project, $issue]);

        if (!in_array($issue->status, [IssueStatus::Open, IssueStatus::UnderReview])) {
            abort(409, 'Issue is not open or under review.');
        }

        $validated = $request->validate([
            'escalation_reason' => ['required', 'string'],
        ]);

        $issue->update([
            'status'            => IssueStatus::Escalated->value,
            'escalated_at'      => now(),
            'escalation_reason' => $validated['escalation_reason'],
        ]);

        return new IssueResource($issue->load(['raisedBy', 'assignedTo']));
    }

    /**
     * Resolve an issue.
     *
     * @response {"data": {"id": 1, "status": "closed"}}
     * @response 409 {"message": "Issue is already closed."}
     */
    public function resolve(Request $request, Project $project, Issue $issue): IssueResource
    {
        $this->authorize('update', [Issue::class, $project, $issue]);

        if ($issue->status === IssueStatus::Closed) {
            abort(409, 'Issue is already closed.');
        }

        $validated = $request->validate([
            'resolution' => ['required', 'string'],
        ]);

        $issue->update([
            'status'      => IssueStatus::Closed->value,
            'resolution'  => $validated['resolution'],
            'resolved_at' => now(),
        ]);

        return new IssueResource($issue->load(['raisedBy', 'assignedTo']));
    }
}
