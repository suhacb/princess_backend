<?php

namespace App\Http\Controllers;

use App\Http\Requests\LessonsLog\LessonRequest;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use App\Models\Project;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @tags Lessons
 */
class LessonController extends Controller
{
    /**
     * List lessons for a project.
     *
     * @response {"data": [{"id": 1, "description": "...", "source": "retrospective"}]}
     */
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Lesson::class, $project]);

        $lessons = $project->lessons()->with('raisedBy')->latest('raised_at')->get();

        return LessonResource::collection($lessons);
    }

    /**
     * Record a lesson.
     *
     * @response 201 {"data": {"id": 1, "description": "..."}}
     */
    public function store(LessonRequest $request, Project $project): LessonResource
    {
        $this->authorize('create', [Lesson::class, $project]);

        $lesson = $project->lessons()->create(array_merge(
            $request->validated(),
            [
                'raised_by' => auth()->user()->person_id,
                'raised_at' => now(),
            ]
        ));

        return new LessonResource($lesson->load('raisedBy'));
    }

    /**
     * Get a lesson.
     *
     * @response {"data": {"id": 1, "description": "..."}}
     */
    public function show(Project $project, Lesson $lesson): LessonResource
    {
        $this->authorize('view', [Lesson::class, $project, $lesson]);

        return new LessonResource($lesson->load('raisedBy'));
    }

    /**
     * Update a lesson.
     *
     * @response {"data": {"id": 1, "description": "Updated"}}
     */
    public function update(LessonRequest $request, Project $project, Lesson $lesson): LessonResource
    {
        $this->authorize('update', [Lesson::class, $project, $lesson]);

        $lesson->update($request->validated());

        return new LessonResource($lesson->load('raisedBy'));
    }

    /**
     * Delete a lesson.
     *
     * @response 204 {}
     */
    public function destroy(Project $project, Lesson $lesson): Response
    {
        $this->authorize('delete', [Lesson::class, $project, $lesson]);

        $lesson->delete();

        return response()->noContent();
    }
}
