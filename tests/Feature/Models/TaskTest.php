<?php

namespace Tests\Feature\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Person;
use App\Models\Project;
use App\Models\Stage;
use App\Models\Task;
use App\Models\WorkPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_task(): void
    {
        Task::factory()->create(['title' => 'Deploy to staging']);

        $this->assertDatabaseHas('tasks', ['title' => 'Deploy to staging']);
    }

    public function test_status_defaults_to_todo(): void
    {
        $task = Task::factory()->create(['status' => TaskStatus::Todo->value]);

        $this->assertEquals(TaskStatus::Todo, $task->status);
    }

    public function test_status_is_cast_to_enum(): void
    {
        $task = Task::factory()->create(['status' => TaskStatus::InProgress->value]);

        $this->assertInstanceOf(TaskStatus::class, $task->fresh()->status);
        $this->assertEquals(TaskStatus::InProgress, $task->fresh()->status);
    }

    public function test_priority_is_cast_to_enum(): void
    {
        $task = Task::factory()->create(['priority' => TaskPriority::Critical->value]);

        $this->assertInstanceOf(TaskPriority::class, $task->fresh()->priority);
        $this->assertEquals(TaskPriority::Critical, $task->fresh()->priority);
    }

    public function test_due_date_is_cast_to_date(): void
    {
        $task = Task::factory()->create(['due_date' => '2026-09-01']);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $task->fresh()->due_date);
    }

    public function test_belongs_to_project(): void
    {
        $project = Project::factory()->create();
        $task    = Task::factory()->create(['project_id' => $project->id]);

        $this->assertTrue($task->project->is($project));
    }

    public function test_belongs_to_stage(): void
    {
        $person  = Person::factory()->create();
        $project = Project::factory()->create(['created_by' => $person->id]);
        $stage   = Stage::factory()->create(['project_id' => $project->id, 'created_by' => $person->id]);
        $task    = Task::factory()->create(['project_id' => $project->id, 'stage_id' => $stage->id]);

        $this->assertTrue($task->stage->is($stage));
    }

    public function test_stage_is_nullable(): void
    {
        $task = Task::factory()->create(['stage_id' => null]);

        $this->assertNull($task->stage);
    }

    public function test_belongs_to_work_package(): void
    {
        $person  = Person::factory()->create();
        $project = Project::factory()->create(['created_by' => $person->id]);
        $wp      = WorkPackage::factory()->create([
            'project_id'      => $project->id,
            'team_manager_id' => $person->id,
            'created_by'      => $person->id,
        ]);
        $task = Task::factory()->create(['project_id' => $project->id, 'work_package_id' => $wp->id]);

        $this->assertTrue($task->workPackage->is($wp));
    }

    public function test_work_package_is_nullable(): void
    {
        $task = Task::factory()->create(['work_package_id' => null]);

        $this->assertNull($task->workPackage);
    }

    public function test_assigned_to_relates_to_person(): void
    {
        $person = Person::factory()->create();
        $task   = Task::factory()->create(['assigned_to' => $person->id]);

        $this->assertTrue($task->assignedTo->is($person));
    }

    public function test_assigned_to_is_nullable(): void
    {
        $task = Task::factory()->create(['assigned_to' => null]);

        $this->assertNull($task->assignedTo);
    }

    public function test_created_by_relates_to_person(): void
    {
        $person = Person::factory()->create();
        $task   = Task::factory()->create(['created_by' => $person->id]);

        $this->assertTrue($task->createdBy->is($person));
    }

    public function test_soft_delete_does_not_remove_record(): void
    {
        $task = Task::factory()->create();
        $task->delete();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_update_is_recorded_in_activity_log(): void
    {
        $task = Task::factory()->create(['status' => TaskStatus::Todo->value]);

        $task->update(['status' => TaskStatus::InProgress->value]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Task::class,
            'subject_id'   => $task->id,
            'event'        => 'updated',
        ]);
    }

    public function test_activity_log_captures_changed_fields_only(): void
    {
        $task = Task::factory()->create(['status' => TaskStatus::Todo->value, 'title' => 'Original']);

        $task->update(['status' => TaskStatus::Done->value]);

        $activity = Activity::where('subject_type', Task::class)
            ->where('subject_id', $task->id)
            ->where('event', 'updated')
            ->first();

        $changes = $activity->attribute_changes->toArray();

        $this->assertArrayHasKey('status', $changes['attributes']);
        $this->assertArrayNotHasKey('title', $changes['attributes']);
    }
}
