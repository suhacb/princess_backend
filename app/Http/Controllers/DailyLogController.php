<?php

namespace App\Http\Controllers;

use App\Http\Requests\DailyLog\DailyLogEntryRequest;
use App\Http\Resources\DailyLogEntryResource;
use App\Models\DailyLogEntry;
use App\Models\Project;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DailyLogController extends Controller
{
    /**
     * List daily log entries for a project.
     *
     * @response {"data": [{"id": 1, "date": "2026-06-09", "entry_type": "note", "body": "..."}]}
     */
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [DailyLogEntry::class, $project]);

        $entries = $project->dailyLogEntries()->with('author')->latest('date')->get();

        return DailyLogEntryResource::collection($entries);
    }

    /**
     * Create a daily log entry.
     *
     * @response 201 {"data": {"id": 1, "entry_type": "note", "body": "..."}}
     */
    public function store(DailyLogEntryRequest $request, Project $project): DailyLogEntryResource
    {
        $this->authorize('create', [DailyLogEntry::class, $project]);

        $entry = $project->dailyLogEntries()->create(array_merge(
            $request->validated(),
            ['author_id' => auth()->user()->person_id],
        ));

        return new DailyLogEntryResource($entry->load('author'));
    }

    /**
     * Get a daily log entry.
     *
     * @response {"data": {"id": 1, "body": "..."}}
     */
    public function show(Project $project, DailyLogEntry $dailyLogEntry): DailyLogEntryResource
    {
        $this->authorize('view', [DailyLogEntry::class, $project, $dailyLogEntry]);

        return new DailyLogEntryResource($dailyLogEntry->load('author'));
    }

    /**
     * Update a daily log entry.
     *
     * @response {"data": {"id": 1, "body": "Updated"}}
     */
    public function update(DailyLogEntryRequest $request, Project $project, DailyLogEntry $dailyLogEntry): DailyLogEntryResource
    {
        $this->authorize('update', [DailyLogEntry::class, $project, $dailyLogEntry]);

        $dailyLogEntry->update($request->validated());

        return new DailyLogEntryResource($dailyLogEntry->load('author'));
    }

    /**
     * Delete a daily log entry.
     *
     * @response 204 {}
     */
    public function destroy(Project $project, DailyLogEntry $dailyLogEntry): Response
    {
        $this->authorize('delete', [DailyLogEntry::class, $project, $dailyLogEntry]);

        $dailyLogEntry->delete();

        return response()->noContent();
    }
}
