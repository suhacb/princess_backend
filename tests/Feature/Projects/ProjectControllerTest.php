<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectStatus;
use App\Models\Person;
use App\Models\Project;
use App\Models\Stage;
use App\Models\User;
use App\Services\Document\ProjectStorageService;
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

        $this->mock(ProjectStorageService::class)
            ->shouldReceive('provision')
            ->andReturnNull();

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

    public function test_store_returns_422_when_name_exceeds_max_length(): void
    {
        $this->postJson('/api/projects', ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_returns_422_when_reference_exceeds_max_length(): void
    {
        $this->postJson('/api/projects', [
            'name'      => 'Test Project',
            'reference' => str_repeat('a', 51),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reference']);
    }

    public function test_store_returns_422_when_status_invalid(): void
    {
        $this->postJson('/api/projects', [
            'name'   => 'Test Project',
            'status' => 'not_a_status',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_store_returns_422_when_planned_start_is_invalid_date(): void
    {
        $this->postJson('/api/projects', [
            'name'          => 'Test Project',
            'planned_start' => 'not-a-date',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['planned_start']);
    }

    public function test_store_returns_422_when_planned_end_before_planned_start(): void
    {
        $this->postJson('/api/projects', [
            'name'          => 'Test Project',
            'planned_start' => '2026-06-10',
            'planned_end'   => '2026-06-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['planned_end']);
    }

    public function test_store_returns_422_when_actual_start_is_invalid_date(): void
    {
        $this->postJson('/api/projects', [
            'name'         => 'Test Project',
            'actual_start' => 'not-a-date',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['actual_start']);
    }

    public function test_store_returns_422_when_actual_end_before_actual_start(): void
    {
        $this->postJson('/api/projects', [
            'name'         => 'Test Project',
            'actual_start' => '2026-06-10',
            'actual_end'   => '2026-06-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['actual_end']);
    }

    public function test_store_returns_422_when_tolerance_field_exceeds_max_length(): void
    {
        foreach ([
            'tolerance_time',
            'tolerance_cost',
            'tolerance_scope',
            'tolerance_risk',
            'tolerance_quality',
            'tolerance_benefit',
        ] as $field) {
            $this->postJson('/api/projects', [
                'name' => 'Test Project',
                $field => str_repeat('a', 256),
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors([$field]);
        }
    }

    public function test_store_creates_project_with_all_optional_fields(): void
    {
        $this->postJson('/api/projects', [
            'name'              => 'Full Project',
            'reference'         => 'REF-001',
            'description'       => 'Some description',
            'status'            => ProjectStatus::Delivery->value,
            'planned_start'     => '2026-06-01',
            'planned_end'       => '2026-06-10',
            'actual_start'      => '2026-06-01',
            'actual_end'        => '2026-06-10',
            'tolerance_time'    => 'low',
            'tolerance_cost'    => 'low',
            'tolerance_scope'   => 'low',
            'tolerance_risk'    => 'low',
            'tolerance_quality' => 'low',
            'tolerance_benefit' => 'low',
        ])
            ->assertCreated()
            ->assertJsonPath('data.reference', 'REF-001')
            ->assertJsonPath('data.status', ProjectStatus::Delivery->value);
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

    public function test_update_succeeds_without_name(): void
    {
        $project = Project::factory()->create(['created_by' => $this->person->id, 'name' => 'Original']);
        $this->addMember($project);

        $this->putJson("/api/projects/{$project->id}", ['description' => 'Updated description'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Original')
            ->assertJsonPath('data.description', 'Updated description');
    }

    public function test_update_returns_422_when_name_is_empty_string(): void
    {
        $project = Project::factory()->create(['created_by' => $this->person->id]);
        $this->addMember($project);

        $this->putJson("/api/projects/{$project->id}", ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_returns_422_when_status_invalid(): void
    {
        $project = Project::factory()->create(['created_by' => $this->person->id]);
        $this->addMember($project);

        $this->putJson("/api/projects/{$project->id}", ['status' => 'not_a_status'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_update_returns_422_when_reference_exceeds_max_length(): void
    {
        $project = Project::factory()->create(['created_by' => $this->person->id]);
        $this->addMember($project);

        $this->putJson("/api/projects/{$project->id}", ['reference' => str_repeat('a', 51)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reference']);
    }

    public function test_update_returns_422_when_planned_end_before_planned_start(): void
    {
        $project = Project::factory()->create(['created_by' => $this->person->id]);
        $this->addMember($project);

        $this->putJson("/api/projects/{$project->id}", [
            'planned_start' => '2026-06-10',
            'planned_end'   => '2026-06-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['planned_end']);
    }

    public function test_update_returns_422_when_actual_end_before_actual_start(): void
    {
        $project = Project::factory()->create(['created_by' => $this->person->id]);
        $this->addMember($project);

        $this->putJson("/api/projects/{$project->id}", [
            'actual_start' => '2026-06-10',
            'actual_end'   => '2026-06-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['actual_end']);
    }

    public function test_update_returns_422_when_tolerance_field_exceeds_max_length(): void
    {
        $project = Project::factory()->create(['created_by' => $this->person->id]);
        $this->addMember($project);

        foreach ([
            'tolerance_time',
            'tolerance_cost',
            'tolerance_scope',
            'tolerance_risk',
            'tolerance_quality',
            'tolerance_benefit',
        ] as $field) {
            $this->putJson("/api/projects/{$project->id}", [$field => str_repeat('a', 256)])
                ->assertUnprocessable()
                ->assertJsonValidationErrors([$field]);
        }
    }

    public function test_update_returns_422_when_version_is_zero(): void
    {
        $project = Project::factory()->create(['created_by' => $this->person->id]);
        $this->addMember($project);

        $this->putJson("/api/projects/{$project->id}", ['version' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['version']);
    }

    public function test_update_succeeds_with_valid_version(): void
    {
        $project = Project::factory()->create(['created_by' => $this->person->id]);
        $this->addMember($project);

        $this->putJson("/api/projects/{$project->id}", ['version' => 3])
            ->assertOk();
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

    public function test_store_calls_storage_provision(): void
    {
        $this->mock(ProjectStorageService::class)
            ->shouldReceive('provision')
            ->once()
            ->andReturnNull();

        $this->postJson('/api/projects', ['name' => 'Provisioned Project'])
            ->assertCreated();
    }

    public function test_store_returns_503_and_removes_project_when_storage_unavailable(): void
    {
        $this->mock(ProjectStorageService::class)
            ->shouldReceive('provision')
            ->andThrow(new \RuntimeException('Garage unreachable'));

        $this->postJson('/api/projects', ['name' => 'Failed Project'])
            ->assertStatus(503);

        $this->assertDatabaseMissing('projects', ['name' => 'Failed Project']);
    }
}
