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

    public function test_store_returns_422_when_name_missing(): void
    {
        $this->postJson("/api/projects/{$this->project->id}/stages", [
            'type' => StageType::Initiation->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_returns_422_when_name_exceeds_max_length(): void
    {
        $this->postJson("/api/projects/{$this->project->id}/stages", [
            'name' => str_repeat('a', 256),
            'type' => StageType::Initiation->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_returns_422_when_type_invalid(): void
    {
        $this->postJson("/api/projects/{$this->project->id}/stages", [
            'name' => 'Initiation',
            'type' => 'not_a_type',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_store_returns_422_when_status_invalid(): void
    {
        $this->postJson("/api/projects/{$this->project->id}/stages", [
            'name'   => 'Initiation',
            'type'   => StageType::Initiation->value,
            'status' => 'not_a_status',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_store_returns_422_when_sequence_is_negative(): void
    {
        $this->postJson("/api/projects/{$this->project->id}/stages", [
            'name'     => 'Initiation',
            'type'     => StageType::Initiation->value,
            'sequence' => -1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sequence']);
    }

    public function test_store_returns_422_when_planned_start_is_invalid_date(): void
    {
        $this->postJson("/api/projects/{$this->project->id}/stages", [
            'name'          => 'Initiation',
            'type'          => StageType::Initiation->value,
            'planned_start' => 'not-a-date',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['planned_start']);
    }

    public function test_store_returns_422_when_planned_end_before_planned_start(): void
    {
        $this->postJson("/api/projects/{$this->project->id}/stages", [
            'name'          => 'Initiation',
            'type'          => StageType::Initiation->value,
            'planned_start' => '2026-06-10',
            'planned_end'   => '2026-06-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['planned_end']);
    }

    public function test_store_returns_422_when_actual_start_is_invalid_date(): void
    {
        $this->postJson("/api/projects/{$this->project->id}/stages", [
            'name'         => 'Initiation',
            'type'         => StageType::Initiation->value,
            'actual_start' => 'not-a-date',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['actual_start']);
    }

    public function test_store_returns_422_when_actual_end_before_actual_start(): void
    {
        $this->postJson("/api/projects/{$this->project->id}/stages", [
            'name'         => 'Initiation',
            'type'         => StageType::Initiation->value,
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
            $this->postJson("/api/projects/{$this->project->id}/stages", [
                'name'  => 'Initiation',
                'type'  => StageType::Initiation->value,
                $field  => str_repeat('a', 256),
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors([$field]);
        }
    }

    public function test_store_creates_stage_with_all_optional_fields(): void
    {
        $this->postJson("/api/projects/{$this->project->id}/stages", [
            'name'              => 'Delivery',
            'type'              => StageType::Delivery->value,
            'sequence'          => 0,
            'description'       => 'Some description',
            'status'            => StageStatus::Active->value,
            'planned_start'     => '2026-06-01',
            'planned_end'       => '2026-06-01',
            'actual_start'      => '2026-06-01',
            'actual_end'        => '2026-06-01',
            'tolerance_time'    => 'low',
            'tolerance_cost'    => 'low',
            'tolerance_scope'   => 'low',
            'tolerance_risk'    => 'low',
            'tolerance_quality' => 'low',
            'tolerance_benefit' => 'low',
        ])
            ->assertCreated()
            ->assertJsonPath('data.sequence', 0)
            ->assertJsonPath('data.status', StageStatus::Active->value);
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

    public function test_update_succeeds_without_type(): void
    {
        $stage = Stage::factory()->create([
            'project_id' => $this->project->id,
            'type'       => StageType::Initiation,
        ]);

        $this->putJson("/api/projects/{$this->project->id}/stages/{$stage->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.type', StageType::Initiation->value);
    }

    public function test_update_returns_422_when_type_invalid(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id]);

        $this->putJson("/api/projects/{$this->project->id}/stages/{$stage->id}", ['type' => 'not_a_type'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_update_returns_422_when_type_is_null(): void
    {
        $stage = Stage::factory()->create([
            'project_id' => $this->project->id,
            'type'       => StageType::Initiation,
        ]);

        $this->putJson("/api/projects/{$this->project->id}/stages/{$stage->id}", ['type' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_update_returns_422_when_name_is_empty_string(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id]);

        $this->putJson("/api/projects/{$this->project->id}/stages/{$stage->id}", ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_returns_422_when_sequence_is_negative(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id]);

        $this->putJson("/api/projects/{$this->project->id}/stages/{$stage->id}", ['sequence' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sequence']);
    }

    public function test_update_returns_422_when_version_is_zero(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id]);

        $this->putJson("/api/projects/{$this->project->id}/stages/{$stage->id}", ['version' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['version']);
    }

    public function test_update_returns_422_when_version_is_negative(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id]);

        $this->putJson("/api/projects/{$this->project->id}/stages/{$stage->id}", ['version' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['version']);
    }

    public function test_update_succeeds_with_valid_version(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id]);

        $this->putJson("/api/projects/{$this->project->id}/stages/{$stage->id}", ['version' => 2])
            ->assertOk()
            ->assertJsonPath('data.version', 2);
    }

    public function test_update_returns_422_when_planned_end_before_planned_start(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id]);

        $this->putJson("/api/projects/{$this->project->id}/stages/{$stage->id}", [
            'planned_start' => '2026-06-10',
            'planned_end'   => '2026-06-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['planned_end']);
    }

    public function test_update_returns_422_when_actual_end_before_actual_start(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id]);

        $this->putJson("/api/projects/{$this->project->id}/stages/{$stage->id}", [
            'actual_start' => '2026-06-10',
            'actual_end'   => '2026-06-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['actual_end']);
    }

    public function test_update_returns_422_when_tolerance_field_exceeds_max_length(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id]);

        foreach ([
            'tolerance_time',
            'tolerance_cost',
            'tolerance_scope',
            'tolerance_risk',
            'tolerance_quality',
            'tolerance_benefit',
        ] as $field) {
            $this->putJson("/api/projects/{$this->project->id}/stages/{$stage->id}", [
                $field => str_repeat('a', 256),
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors([$field]);
        }
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
