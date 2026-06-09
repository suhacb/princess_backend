<?php

namespace Tests\Feature\Projects;

use App\Enums\StageStatus;
use App\Enums\StageType;
use App\Models\Person;
use App\Models\Project;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Person $person;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\VerifyFrontend::class);

        $this->person  = Person::factory()->create();
        $this->user    = User::factory()->create(['person_id' => $this->person->id]);
        $this->actingAs($this->user);

        $this->project = Project::factory()->create(['created_by' => $this->person->id]);
        $this->project->members()->create([
            'person_id' => $this->person->id,
            'role'      => 'project_manager',
        ]);
    }

    public function test_index_lists_stages_for_project(): void
    {
        Stage::factory()->count(3)->create(['project_id' => $this->project->id]);

        $this->getJson("/api/projects/{$this->project->id}/stages")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_store_creates_stage(): void
    {
        $this->postJson("/api/projects/{$this->project->id}/stages", [
            'name' => 'Initiation',
            'type' => StageType::Initiation->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Initiation')
            ->assertJsonPath('data.type', StageType::Initiation->value)
            ->assertJsonPath('data.status', StageStatus::Planned->value);
    }

    public function test_store_sets_created_by(): void
    {
        $this->postJson("/api/projects/{$this->project->id}/stages", [
            'name' => 'Initiation',
            'type' => StageType::Initiation->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.created_by.id', $this->person->id);
    }

    public function test_store_returns_422_when_type_missing(): void
    {
        $this->postJson("/api/projects/{$this->project->id}/stages", ['name' => 'Initiation'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_show_returns_stage(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id]);

        $this->getJson("/api/projects/{$this->project->id}/stages/{$stage->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $stage->id);
    }

    public function test_show_returns_404_for_stage_belonging_to_another_project(): void
    {
        $otherProject = Project::factory()->create();
        $stage        = Stage::factory()->create(['project_id' => $otherProject->id]);

        $this->getJson("/api/projects/{$this->project->id}/stages/{$stage->id}")
            ->assertNotFound();
    }

    public function test_update_modifies_stage(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id]);

        $this->putJson("/api/projects/{$this->project->id}/stages/{$stage->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');
    }

    public function test_destroy_soft_deletes_stage(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id]);

        $this->deleteJson("/api/projects/{$this->project->id}/stages/{$stage->id}")->assertNoContent();

        $this->assertSoftDeleted('stages', ['id' => $stage->id]);
    }

    public function test_transition_changes_stage_status(): void
    {
        $stage = Stage::factory()->create([
            'project_id' => $this->project->id,
            'status'     => StageStatus::Planned,
        ]);

        $this->patchJson("/api/projects/{$this->project->id}/stages/{$stage->id}/transition", [
            'status' => StageStatus::Active->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', StageStatus::Active->value);
    }

    public function test_transition_returns_422_for_invalid_status(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id]);

        $this->patchJson("/api/projects/{$this->project->id}/stages/{$stage->id}/transition", [
            'status' => 'invalid_status',
        ])->assertUnprocessable();
    }
}
