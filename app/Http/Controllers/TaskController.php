<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;

/**
 * @tags Tasks
 */
class TaskController extends Controller
{
    /**
     * List tasks for a project.
     *
     * @queryParam stage_id integer Filter by stage ID. No-example
     * @queryParam work_package_id integer Filter by work package ID. No-example
     * @queryParam assigned_to integer Filter by person ID. No-example
     * @queryParam status string Filter by status (todo/in_progress/done/blocked). No-example
     * @queryParam priority string Filter by priority (low/medium/high/critical). No-example
     * @response {"data": [{"id": 1, "title": "...", "status": "todo", "priority": "medium"}]}
     */
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Task::class, $project]);

        $query = $project->tasks()->with(['assignedTo', 'createdBy']);

        if ($request->filled('stage_id')) {
            $query->where('stage_id', $request->integer('stage_id'));
        }

        if ($request->filled('work_package_id')) {
            $query->where('work_package_id', $request->integer('work_package_id'));
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->integer('assigned_to'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->string('priority'));
        }

        return TaskResource::collection($query->latest()->get());
    }

    /**
     * Create a task.
     *
     * @response 201 {"data": {"id": 1, "title": "...", "status": "todo"}}
     */
    public function store(StoreTaskRequest $request, Project $project): TaskResource
    {
        $this->authorize('create', [Task::class, $project]);

        $validated = $request->validated();

        $this->assertBelongsToProject($project, $validated);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $task = $project->tasks()->create(array_merge(
            $validated,
            [
                'status'     => $validated['status'] ?? TaskStatus::Todo->value,
                'priority'   => $validated['priority'] ?? TaskPriority::Medium->value,
                'created_by' => $user->person_id,
            ]
        ));

        return new TaskResource($task->load(['assignedTo', 'stage', 'workPackage', 'createdBy']));
    }

    /**
     * Get a task.
     *
     * @response {"data": {"id": 1, "title": "..."}}
     */
    public function show(Project $project, Task $task): TaskResource
    {
        $this->authorize('view', [Task::class, $project, $task]);

        return new TaskResource($task->load(['assignedTo', 'stage', 'workPackage', 'createdBy', 'updatedBy']));
    }

    /**
     * Update a task.
     *
     * @response {"data": {"id": 1, "title": "Updated"}}
     */
    public function update(UpdateTaskRequest $request, Project $project, Task $task): TaskResource
    {
        $this->authorize('update', [Task::class, $project, $task]);

        $validated = $request->validated();

        $this->assertBelongsToProject($project, $validated);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $task->update(array_merge(
            $validated,
            ['updated_by' => $user->person_id]
        ));

        return new TaskResource($task->load(['assignedTo', 'stage', 'workPackage', 'createdBy', 'updatedBy']));
    }

    /**
     * Delete a task.
     *
     * @response 204 {}
     */
    public function destroy(Project $project, Task $task): Response
    {
        $this->authorize('delete', [Task::class, $project, $task]);

        $task->delete();

        return response()->noContent();
    }

    /**
     * Get the change history of a task.
     *
     * @response {"data": [{"event": "updated", "causer": {"id": 1, "name": "Ana Novak"}, "occurred_at": "2026-06-27T10:00:00Z", "changes": {"status": {"old": "todo", "new": "in_progress"}}}]}
     */
    public function history(Project $project, Task $task): \Illuminate\Http\JsonResponse
    {
        $this->authorize('history', [Task::class, $project, $task]);

        $activities = ActivityLog::query()
            ->where('subject_type', Task::class)
            ->where('subject_id', $task->id)
            ->with(['causer.person'])
            ->latest()
            ->get();

        $data = $activities->map(function (ActivityLog $activity) {
            $causer  = $activity->causer;
            $changes = $activity->attribute_changes;

            return [
                'event'       => $activity->event,
                'causer'      => $causer ? [
                    'id'   => $causer->person_id,
                    'name' => $causer->person?->name ?? $causer->email,
                ] : null,
                'occurred_at' => $activity->created_at,
                'changes'     => $this->formatChanges($changes?->toArray() ?? []),
            ];
        });

        return response()->json(['data' => $data]);
    }

    private function formatChanges(array $changes): array
    {
        $old        = $changes['old'] ?? [];
        $attributes = $changes['attributes'] ?? [];

        if (empty($old) && empty($attributes)) {
            return $changes;
        }

        $formatted = [];
        foreach ($attributes as $key => $newValue) {
            $formatted[$key] = [
                'old' => $old[$key] ?? null,
                'new' => $newValue,
            ];
        }

        foreach ($old as $key => $oldValue) {
            if (! isset($formatted[$key])) {
                $formatted[$key] = [
                    'old' => $oldValue,
                    'new' => $attributes[$key] ?? null,
                ];
            }
        }

        return $formatted;
    }

    private function assertBelongsToProject(Project $project, array $validated): void
    {
        if (! empty($validated['stage_id'])) {
            abort_if(
                ! $project->stages()->where('id', $validated['stage_id'])->exists(),
                422,
                'Stage must belong to this project.'
            );
        }

        if (! empty($validated['work_package_id'])) {
            abort_if(
                ! $project->workPackages()->where('id', $validated['work_package_id'])->exists(),
                422,
                'Work package must belong to this project.'
            );
        }

        if (! empty($validated['assigned_to'])) {
            abort_if(
                ! $project->members()->where('person_id', $validated['assigned_to'])->exists(),
                422,
                'Assigned person must be a member of this project.'
            );
        }
    }
}
