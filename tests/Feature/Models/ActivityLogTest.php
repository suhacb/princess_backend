<?php

namespace Tests\Feature\Models;

use App\Models\ActivityLog;
use App\Models\Person;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function makeTaskActivity(): ActivityLog
    {
        $person  = Person::factory()->create();
        $project = Project::factory()->create(['created_by' => $person->id]);
        $task    = Task::factory()->create(['project_id' => $project->id, 'created_by' => $person->id]);
        $task->update(['title' => 'Changed']);

        /** @var ActivityLog $activity */
        $activity = ActivityLog::where('subject_type', Task::class)
            ->where('subject_id', $task->id)
            ->where('event', 'updated')
            ->firstOrFail();

        return $activity;
    }

    public function test_activity_log_entry_is_created_on_model_update(): void
    {
        $activity = $this->makeTaskActivity();

        $this->assertInstanceOf(ActivityLog::class, $activity);
        $this->assertEquals('updated', $activity->event);
    }

    public function test_activity_log_entry_cannot_be_updated(): void
    {
        $activity = $this->makeTaskActivity();

        $this->expectException(\LogicException::class);

        $activity->description = 'tampered';
        $activity->save();
    }

    public function test_activity_log_entry_cannot_be_deleted(): void
    {
        $activity = $this->makeTaskActivity();

        $this->expectException(\LogicException::class);

        $activity->delete();
    }

    public function test_project_id_is_stored_in_properties(): void
    {
        $person  = Person::factory()->create();
        $project = Project::factory()->create(['created_by' => $person->id]);
        $task    = Task::factory()->create(['project_id' => $project->id, 'created_by' => $person->id]);
        $task->update(['title' => 'Scoped']);

        /** @var ActivityLog $activity */
        $activity = ActivityLog::where('subject_type', Task::class)
            ->where('subject_id', $task->id)
            ->where('event', 'updated')
            ->firstOrFail();

        $this->assertEquals($project->id, $activity->properties->get('project_id'));
    }
}
