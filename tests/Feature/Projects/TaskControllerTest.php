<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Person;
use App\Models\Project;
use App\Models\Stage;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Person $person;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\VerifyFrontend::class);

        $this->person = Person::factory()->create();
        $this->user   = User::factory()->create(['person_id' => $this->person->id]);
        $this->actingAs($this->user);

        $this->project = Project::factory()->create(['created_by' => $this->person->id]);
        $this->project->members()->create([
            'person_id' => $this->person->id,
            'role'      => ProjectRole::ProjectManager->value,
        ]);
    }

    private function indexUrl(): string
    {
        return "/api/projects/{$this->project->id}/tasks";
    }

    private function taskUrl(Task $task): string
    {
        return "/api/projects/{$this->project->id}/tasks/{$task->id}";
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Deploy to staging',
        ], $overrides);
    }

    private function makeTask(array $attributes = []): Task
    {
        return Task::factory()->create(array_merge([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ], $attributes));
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_lists_tasks(): void
    {
        $this->makeTask();
        $this->makeTask();

        $this->getJson($this->indexUrl())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_status(): void
    {
        $this->makeTask(['status' => TaskStatus::Todo->value]);
        $this->makeTask(['status' => TaskStatus::Done->value]);

        $this->getJson($this->indexUrl() . '?status=todo')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_priority(): void
    {
        $this->makeTask(['priority' => TaskPriority::High->value]);
        $this->makeTask(['priority' => TaskPriority::Low->value]);

        $this->getJson($this->indexUrl() . '?priority=high')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_assigned_to(): void
    {
        $assignee = Person::factory()->create();
        $this->project->members()->create(['person_id' => $assignee->id, 'role' => ProjectRole::TeamMember->value]);

        $this->makeTask(['assigned_to' => $assignee->id]);
        $this->makeTask();

        $this->getJson($this->indexUrl() . "?assigned_to={$assignee->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_forbidden_for_non_member(): void
    {
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->indexUrl())
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_task_with_defaults(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.title', 'Deploy to staging')
            ->assertJsonPath('data.status', TaskStatus::Todo->value)
            ->assertJsonPath('data.priority', TaskPriority::Medium->value);

        $this->assertDatabaseHas('tasks', [
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);
    }

    public function test_store_accepts_explicit_status_and_priority(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload([
            'status'   => TaskStatus::InProgress->value,
            'priority' => TaskPriority::Critical->value,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status', TaskStatus::InProgress->value)
            ->assertJsonPath('data.priority', TaskPriority::Critical->value);
    }

    public function test_store_links_stage_and_work_package(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->person->id]);
        $wp    = WorkPackage::factory()->create([
            'project_id'      => $this->project->id,
            'team_manager_id' => $this->person->id,
            'created_by'      => $this->person->id,
        ]);

        $this->postJson($this->indexUrl(), $this->validPayload([
            'stage_id'        => $stage->id,
            'work_package_id' => $wp->id,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.stage_id', $stage->id)
            ->assertJsonPath('data.work_package_id', $wp->id);
    }

    public function test_store_links_assigned_project_member(): void
    {
        $assignee = Person::factory()->create();
        $this->project->members()->create(['person_id' => $assignee->id, 'role' => ProjectRole::TeamMember->value]);

        $this->postJson($this->indexUrl(), $this->validPayload(['assigned_to' => $assignee->id]))
            ->assertCreated()
            ->assertJsonPath('data.assigned_to', $assignee->id);
    }

    public function test_store_requires_title(): void
    {
        $this->postJson($this->indexUrl(), ['title' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_store_rejects_invalid_status(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['status' => 'flying']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_store_rejects_invalid_priority(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['priority' => 'extreme']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('priority');
    }

    public function test_store_rejects_stage_from_another_project(): void
    {
        $otherProject = Project::factory()->create(['created_by' => $this->person->id]);
        $stage        = Stage::factory()->create(['project_id' => $otherProject->id, 'created_by' => $this->person->id]);

        $this->postJson($this->indexUrl(), $this->validPayload(['stage_id' => $stage->id]))
            ->assertUnprocessable();
    }

    public function test_store_rejects_work_package_from_another_project(): void
    {
        $otherProject = Project::factory()->create(['created_by' => $this->person->id]);
        $wp           = WorkPackage::factory()->create([
            'project_id'      => $otherProject->id,
            'team_manager_id' => $this->person->id,
            'created_by'      => $this->person->id,
        ]);

        $this->postJson($this->indexUrl(), $this->validPayload(['work_package_id' => $wp->id]))
            ->assertUnprocessable();
    }

    public function test_store_rejects_assigned_to_non_member(): void
    {
        $outsider = Person::factory()->create();

        $this->postJson($this->indexUrl(), $this->validPayload(['assigned_to' => $outsider->id]))
            ->assertUnprocessable();
    }

    public function test_store_accepts_description(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['description' => 'Some details about the task']))
            ->assertCreated()
            ->assertJsonPath('data.description', 'Some details about the task');
    }

    public function test_store_rejects_non_string_description(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['description' => ['not', 'a', 'string']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('description');
    }

    public function test_store_rejects_non_integer_stage_id(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['stage_id' => 'abc']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stage_id');
    }

    public function test_store_rejects_nonexistent_stage_id(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['stage_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stage_id');
    }

    public function test_store_rejects_non_integer_work_package_id(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['work_package_id' => 'abc']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('work_package_id');
    }

    public function test_store_rejects_nonexistent_work_package_id(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['work_package_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('work_package_id');
    }

    public function test_store_rejects_non_integer_assigned_to(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['assigned_to' => 'abc']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to');
    }

    public function test_store_rejects_nonexistent_assigned_to(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['assigned_to' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to');
    }

    public function test_store_accepts_due_date(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['due_date' => '2026-08-01']))
            ->assertCreated();

        $this->assertDatabaseHas('tasks', [
            'title'    => 'Deploy to staging',
            'due_date' => '2026-08-01',
        ]);
    }

    public function test_store_rejects_invalid_due_date(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['due_date' => 'not-a-date']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('due_date');
    }

    public function test_store_forbidden_for_observer(): void
    {
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create([
            'person_id' => $observerPerson->id,
            'role'      => ProjectRole::Observer->value,
        ]);

        $this->actingAs($observer)
            ->postJson($this->indexUrl(), $this->validPayload())
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_task(): void
    {
        $task = $this->makeTask();

        $this->getJson($this->taskUrl($task))
            ->assertOk()
            ->assertJsonPath('data.id', $task->id);
    }

    public function test_show_forbidden_for_non_member(): void
    {
        $task     = $this->makeTask();
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->taskUrl($task))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // update (PATCH)
    // -------------------------------------------------------------------------

    public function test_update_changes_status(): void
    {
        $task = $this->makeTask(['status' => TaskStatus::Todo->value]);

        $this->patchJson($this->taskUrl($task), ['status' => TaskStatus::InProgress->value])
            ->assertOk()
            ->assertJsonPath('data.status', TaskStatus::InProgress->value);
    }

    public function test_update_sets_updated_by(): void
    {
        $task = $this->makeTask();

        $this->patchJson($this->taskUrl($task), ['title' => 'Updated title'])->assertOk();

        $this->assertDatabaseHas('tasks', [
            'id'         => $task->id,
            'updated_by' => $this->person->id,
        ]);
    }

    public function test_update_rejects_null_title(): void
    {
        $task = $this->makeTask();

        $this->patchJson($this->taskUrl($task), ['title' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_update_rejects_invalid_status(): void
    {
        $task = $this->makeTask();

        $this->patchJson($this->taskUrl($task), ['status' => 'flying'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_update_accepts_valid_priority(): void
    {
        $task = $this->makeTask(['priority' => TaskPriority::Low->value]);

        $this->patchJson($this->taskUrl($task), ['priority' => TaskPriority::Critical->value])
            ->assertOk()
            ->assertJsonPath('data.priority', TaskPriority::Critical->value);
    }

    public function test_update_rejects_invalid_priority(): void
    {
        $task = $this->makeTask();

        $this->patchJson($this->taskUrl($task), ['priority' => 'extreme'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('priority');
    }

    public function test_update_forbidden_for_observer(): void
    {
        $task           = $this->makeTask();
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create([
            'person_id' => $observerPerson->id,
            'role'      => ProjectRole::Observer->value,
        ]);

        $this->actingAs($observer)
            ->patchJson($this->taskUrl($task), ['status' => TaskStatus::Done->value])
            ->assertForbidden();
    }

    public function test_team_member_can_update_own_assigned_task(): void
    {
        $memberPerson = Person::factory()->create();
        $memberUser   = User::factory()->create(['person_id' => $memberPerson->id]);
        $this->project->members()->create([
            'person_id' => $memberPerson->id,
            'role'      => ProjectRole::TeamMember->value,
        ]);

        $task = $this->makeTask(['assigned_to' => $memberPerson->id]);

        $this->actingAs($memberUser)
            ->patchJson($this->taskUrl($task), ['status' => TaskStatus::Done->value])
            ->assertOk()
            ->assertJsonPath('data.status', TaskStatus::Done->value);
    }

    public function test_team_member_cannot_update_unassigned_task(): void
    {
        $memberPerson = Person::factory()->create();
        $memberUser   = User::factory()->create(['person_id' => $memberPerson->id]);
        $this->project->members()->create([
            'person_id' => $memberPerson->id,
            'role'      => ProjectRole::TeamMember->value,
        ]);

        $task = $this->makeTask();

        $this->actingAs($memberUser)
            ->patchJson($this->taskUrl($task), ['status' => TaskStatus::Done->value])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_soft_deletes_task(): void
    {
        $task = $this->makeTask();

        $this->deleteJson($this->taskUrl($task))->assertNoContent();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_destroy_forbidden_for_non_member(): void
    {
        $task     = $this->makeTask();
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->deleteJson($this->taskUrl($task))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // history
    // -------------------------------------------------------------------------

    public function test_history_returns_activity_log(): void
    {
        $task = $this->makeTask();
        $task->update(['status' => TaskStatus::InProgress->value, 'updated_by' => $this->person->id]);

        $this->getJson("/api/projects/{$this->project->id}/tasks/{$task->id}/history")
            ->assertOk()
            ->assertJsonStructure(['data' => [['event', 'causer', 'occurred_at', 'changes']]]);
    }

    public function test_history_forbidden_for_non_member(): void
    {
        $task     = $this->makeTask();
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson("/api/projects/{$this->project->id}/tasks/{$task->id}/history")
            ->assertForbidden();
    }
}
