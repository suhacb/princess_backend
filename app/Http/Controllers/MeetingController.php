<?php

namespace App\Http\Controllers;

use App\Http\Requests\Meeting\MeetingRequest;
use App\Http\Resources\MeetingResource;
use App\Models\Meeting;
use App\Models\Project;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * @tags Meetings
 */
class MeetingController extends Controller
{
    /**
     * List meetings for a project.
     *
     * @response {"data": [{"id": 1, "title": "Kick-off", "date_time": "2026-07-01T10:00:00Z", "action_items_open": 2, "action_items_closed": 1}]}
     */
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Meeting::class, $project]);

        $meetings = $project->meetings()
            ->withCount([
                'actionItems as action_items_open'   => fn ($q) => $q->where('status', 'open'),
                'actionItems as action_items_closed' => fn ($q) => $q->where('status', 'closed'),
            ])
            ->with(['attendees', 'createdBy'])
            ->orderByDesc('date_time')
            ->get();

        return MeetingResource::collection($meetings);
    }

    /**
     * Create a meeting.
     *
     * @response 201 {"data": {"id": 1, "title": "Kick-off"}}
     */
    public function store(MeetingRequest $request, Project $project): MeetingResource
    {
        $this->authorize('create', [Meeting::class, $project]);

        $validated    = $request->validated();
        $attendeeIds  = $validated['attendee_ids'] ?? [];
        unset($validated['attendee_ids']);

        $this->assertAttendeesAreMembersOfProject($project, $attendeeIds);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $meeting = $project->meetings()->create(array_merge(
            $validated,
            ['created_by' => $user->person_id]
        ));

        if ($attendeeIds) {
            $meeting->attendees()->sync($attendeeIds);
        }

        return new MeetingResource($meeting->load(['attendees', 'actionItems.owner', 'createdBy']));
    }

    /**
     * Get a meeting.
     *
     * @response {"data": {"id": 1, "title": "Kick-off", "attendees": [], "action_items": []}}
     */
    public function show(Project $project, Meeting $meeting): MeetingResource
    {
        $this->authorize('view', [Meeting::class, $project, $meeting]);

        return new MeetingResource($meeting->load(['attendees', 'actionItems.owner', 'createdBy', 'updatedBy']));
    }

    /**
     * Update a meeting.
     *
     * @response {"data": {"id": 1, "title": "Updated"}}
     */
    public function update(MeetingRequest $request, Project $project, Meeting $meeting): MeetingResource
    {
        $this->authorize('update', [Meeting::class, $project, $meeting]);

        $validated   = $request->validated();
        $attendeeIds = array_key_exists('attendee_ids', $validated) ? $validated['attendee_ids'] : null;
        unset($validated['attendee_ids']);

        if ($attendeeIds !== null) {
            $this->assertAttendeesAreMembersOfProject($project, $attendeeIds);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $meeting->update(array_merge(
            $validated,
            ['updated_by' => $user->person_id]
        ));

        if ($attendeeIds !== null) {
            $meeting->attendees()->sync($attendeeIds);
        }

        return new MeetingResource($meeting->load(['attendees', 'actionItems.owner', 'createdBy', 'updatedBy']));
    }

    /**
     * Delete a meeting.
     *
     * @response 204 {}
     */
    public function destroy(Project $project, Meeting $meeting): Response
    {
        $this->authorize('delete', [Meeting::class, $project, $meeting]);

        $meeting->delete();

        return response()->noContent();
    }

    private function assertAttendeesAreMembersOfProject(Project $project, array $attendeeIds): void
    {
        if (empty($attendeeIds)) {
            return;
        }

        $memberCount = $project->members()->whereIn('person_id', $attendeeIds)->count();

        abort_if(
            $memberCount !== count($attendeeIds),
            422,
            'All attendees must be members of this project.'
        );
    }
}
