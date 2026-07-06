<?php

namespace App\Http\Controllers;

use App\Enums\MeetingActionItemStatus;
use App\Http\Requests\Meeting\StoreMeetingActionItemRequest;
use App\Http\Requests\Meeting\UpdateMeetingActionItemRequest;
use App\Http\Resources\MeetingActionItemResource;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\Project;
use Illuminate\Http\Response;

/**
 * @tags Meetings
 */
class MeetingActionItemController extends Controller
{
    /**
     * Add an action item to a meeting.
     *
     * @response 201 {"data": {"id": 1, "description": "Follow up with vendor", "status": "open"}}
     */
    public function store(StoreMeetingActionItemRequest $request, Project $project, Meeting $meeting): MeetingActionItemResource
    {
        $this->authorize('create', [MeetingActionItem::class, $project, $meeting]);

        $validated = $request->validated();

        $this->assertOwnerIsMemberOfProject($project, $validated['owner_id']);

        $item = $meeting->actionItems()->create(array_merge(
            $validated,
            ['status' => $validated['status'] ?? MeetingActionItemStatus::Open->value]
        ));

        return new MeetingActionItemResource($item->load('owner'));
    }

    /**
     * Update an action item.
     *
     * @response {"data": {"id": 1, "status": "closed"}}
     */
    public function update(UpdateMeetingActionItemRequest $request, Project $project, Meeting $meeting, MeetingActionItem $actionItem): MeetingActionItemResource
    {
        $this->authorize('update', [MeetingActionItem::class, $project, $meeting, $actionItem]);

        $validated = $request->validated();

        if (! empty($validated['owner_id'])) {
            $this->assertOwnerIsMemberOfProject($project, $validated['owner_id']);
        }

        $actionItem->update($validated);

        return new MeetingActionItemResource($actionItem->load('owner'));
    }

    /**
     * Delete an action item.
     *
     * @response 204 {}
     */
    public function destroy(Project $project, Meeting $meeting, MeetingActionItem $actionItem): Response
    {
        $this->authorize('delete', [MeetingActionItem::class, $project, $meeting, $actionItem]);

        $actionItem->delete();

        return response()->noContent();
    }

    private function assertOwnerIsMemberOfProject(Project $project, int $ownerId): void
    {
        abort_if(
            ! $project->members()->where('person_id', $ownerId)->exists(),
            422,
            'Action item owner must be a member of this project.'
        );
    }
}
