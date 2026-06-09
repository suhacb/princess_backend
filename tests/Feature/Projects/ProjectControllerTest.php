<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectStatus;
use App\Models\Person;
use App\Models\Project;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Person $person;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\VerifyFrontend::class);

        $this->person = Person::factory()->create();
        $this->user   = User::factory()->create(['person_id' => $this->person->id]);
        $this->actingAs($this->user);
    }

    private function addMember(Project $project, string $role = 'project_manager'): void
    {
        $project->members()->create([
            'person_id' => $this->person->id,
            'role'      => $role,
        ]);
    }

    public function test_index_returns_paginated_projects(): void
    {
        Project::factory()->count(3)->create();

        $this->getJson('/api/projects')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonCount(3, 'data');
    }

    public function test_store_creates_project(): void
    {
        $this->postJson('/api/projects', ['name' => 'Core Banking'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Core Banking')
            ->assertJsonPath('data.status', ProjectStatus::PreProject->value);
    }

    public function test_store_sets_created_by_to_current_user_person(): void
    {
        $this->postJson('/api/projects', ['name' => 'Test Project'])
            ->assertCreated()
            ->assertJsonPath('data.created_by.id', $this->person->id);
    }

    public function test_store_adds_creator_as_project_manager(): void
    {
        $response = $this->postJson('/api/projects', ['name' => 'Test Project'])
            ->assertCreated();

        $projectId = $response->json('data.id');

        $this->assertDatabaseHas('project_members', [
            'project_id' => $projectId,
            'person_id'  => $this->person->id,
            'role'       => 'project_manager',
        ]);
    }

    public function test_store_returns_422_when_name_missing(): void
    {
        $this->postJson('/api/projects', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_show_returns_project_with_stages(): void
    {
        $project = Project::factory()->create(['created_by' => $this->person->id]);
        $this->addMember($project);
        Stage::factory()->count(2)->create(['project_id' => $project->id]);

        $this->getJson("/api/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $project->id)
            ->assertJsonCount(2, 'data.stages');
    }

    public function test_show_returns_404_for_unknown_project(): void
    {
        $this->getJson('/api/projects/999')->assertNotFound();
    }

    public function test_show_returns_403_when_not_a_member(): void
    {
        $project = Project::factory()->create();

        $this->getJson("/api/projects/{$project->id}")->assertForbidden();
    }

    public function test_update_modifies_project(): void
    {
        $project = Project::factory()->create(['created_by' => $this->person->id]);
        $this->addMember($project);

        $this->putJson("/api/projects/{$project->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');
    }

    public function test_update_sets_updated_by(): void
    {
        $project = Project::factory()->create(['created_by' => $this->person->id]);
        $this->addMember($project);

        $this->putJson("/api/projects/{$project->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.updated_by.id', $this->person->id);
    }

    public function test_destroy_soft_deletes_project(): void
    {
        $project = Project::factory()->create(['created_by' => $this->person->id]);
        $this->addMember($project);

        $this->deleteJson("/api/projects/{$project->id}")->assertNoContent();

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_set_current_stage_updates_project(): void
    {
        $project = Project::factory()->create(['created_by' => $this->person->id]);
        $this->addMember($project);
        $stage = Stage::factory()->create(['project_id' => $project->id]);

        $this->patchJson("/api/projects/{$project->id}/current-stage", ['stage_id' => $stage->id])
            ->assertOk()
            ->assertJsonPath('data.current_stage.id', $stage->id);
    }

    public function test_set_current_stage_rejects_stage_from_another_project(): void
    {
        $project      = Project::factory()->create(['created_by' => $this->person->id]);
        $this->addMember($project);
        $otherProject = Project::factory()->create();
        $stage        = Stage::factory()->create(['project_id' => $otherProject->id]);

        $this->patchJson("/api/projects/{$project->id}/current-stage", ['stage_id' => $stage->id])
            ->assertNotFound();
    }
}
