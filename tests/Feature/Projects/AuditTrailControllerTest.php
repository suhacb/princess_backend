<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Models\Person;
use App\Models\Project;
use App\Models\Task;
use App\Models\Risk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailControllerTest extends TestCase
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

    private function url(): string
    {
        return "/api/projects/{$this->project->id}/audit-trail";
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

    public function test_index_returns_paginated_activity_for_project(): void
    {
        $task = $this->makeTask(['title' => 'Original']);
        $task->update(['title' => 'Updated']);

        $response = $this->getJson($this->url())->assertOk();

        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayHasKey('meta', $response->json());
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_index_excludes_activity_from_other_projects(): void
    {
        $otherProject = Project::factory()->create(['created_by' => $this->person->id]);
        $otherTask    = Task::factory()->create([
            'project_id' => $otherProject->id,
            'created_by' => $this->person->id,
        ]);
        $otherTask->update(['title' => 'Other project task']);

        $task = $this->makeTask();
        $task->update(['title' => 'This project task']);

        $response = $this->getJson($this->url())->assertOk();

        $taskEntityIds = collect($response->json('data'))
            ->where('entity_type', 'task')
            ->pluck('entity_id')
            ->all();

        $this->assertContains($task->id, $taskEntityIds);
        $this->assertNotContains($otherTask->id, $taskEntityIds);
    }

    public function test_index_returns_correct_shape(): void
    {
        $task = $this->makeTask(['title' => 'My Task']);
        $task->update(['title' => 'Renamed Task']);

        $response = $this->getJson($this->url())->assertOk();

        $entry = collect($response->json('data'))->first(fn ($e) => $e['event'] === 'updated');
        $this->assertNotNull($entry);
        $this->assertArrayHasKey('id', $entry);
        $this->assertArrayHasKey('entity_type', $entry);
        $this->assertArrayHasKey('entity_id', $entry);
        $this->assertArrayHasKey('entity_title', $entry);
        $this->assertArrayHasKey('event', $entry);
        $this->assertArrayHasKey('causer', $entry);
        $this->assertArrayHasKey('occurred_at', $entry);
        $this->assertArrayHasKey('changes', $entry);
        $this->assertEquals('task', $entry['entity_type']);
        $this->assertEquals($task->id, $entry['entity_id']);
        $this->assertEquals('Renamed Task', $entry['entity_title']);
    }

    public function test_index_includes_changes_in_correct_format(): void
    {
        $task = $this->makeTask(['title' => 'Before']);
        $task->update(['title' => 'After']);

        $response = $this->getJson($this->url())->assertOk();

        $entry = collect($response->json('data'))->first(fn ($e) => $e['event'] === 'updated');
        $this->assertArrayHasKey('title', $entry['changes']);
        $this->assertEquals('Before', $entry['changes']['title']['old']);
        $this->assertEquals('After', $entry['changes']['title']['new']);
    }

    public function test_index_includes_causer(): void
    {
        $task = $this->makeTask();
        $task->update(['title' => 'Changed']);

        $response = $this->getJson($this->url())->assertOk();

        $entry = collect($response->json('data'))->first(fn ($e) => $e['event'] === 'updated');
        $this->assertEquals($this->person->id, $entry['causer']['id']);
        $this->assertEquals($this->person->name, $entry['causer']['name']);
    }

    public function test_index_filter_by_entity_type(): void
    {
        $task = $this->makeTask();
        $task->update(['title' => 'Task changed']);

        $risk = Risk::factory()->create(['project_id' => $this->project->id]);
        $risk->update(['title' => 'Risk changed']);

        $response = $this->getJson($this->url() . '?entity_type=task')->assertOk();

        $types = collect($response->json('data'))->pluck('entity_type')->unique()->values();
        $this->assertEquals(['task'], $types->all());
    }

    public function test_index_filter_by_actor(): void
    {
        $otherPerson = Person::factory()->create();
        /** @var User $otherUser */
        $otherUser   = User::factory()->create(['person_id' => $otherPerson->id]);
        $this->project->members()->create(['person_id' => $otherPerson->id, 'role' => ProjectRole::TeamMember->value]);

        $task = $this->makeTask();
        $task->update(['title' => 'Changed by PM']);

        $this->actingAs($otherUser);
        $task->update(['title' => 'Changed by other']);

        $response = $this->actingAs($this->user)
            ->getJson($this->url() . "?actor={$this->person->id}")
            ->assertOk();

        $causerIds = collect($response->json('data'))->pluck('causer.id')->unique()->values();
        $this->assertEquals([$this->person->id], $causerIds->all());
    }

    public function test_index_filter_by_date_range(): void
    {
        $task = $this->makeTask();
        $task->update(['title' => 'Changed']);

        $response = $this->getJson($this->url() . '?from=2025-01-01&to=2030-12-31')->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')));

        $response = $this->getJson($this->url() . '?from=2020-01-01&to=2020-12-31')->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_index_rejects_invalid_entity_type(): void
    {
        $this->getJson($this->url() . '?entity_type=banana')->assertUnprocessable();
    }

    public function test_index_forbidden_for_non_member(): void
    {
        /** @var User $stranger */
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)->getJson($this->url())->assertForbidden();
    }

    public function test_index_meta_contains_pagination(): void
    {
        $response = $this->getJson($this->url())->assertOk();

        $meta = $response->json('meta');
        $this->assertArrayHasKey('current_page', $meta);
        $this->assertArrayHasKey('last_page', $meta);
        $this->assertArrayHasKey('per_page', $meta);
        $this->assertArrayHasKey('total', $meta);
    }
}
