<?php

namespace Tests\Feature\Models;

use App\Enums\StageStatus;
use App\Enums\StageType;
use App\Models\Person;
use App\Models\Project;
use App\Models\Stage;
use App\Models\StageBoundary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_stage(): void
    {
        $stage = Stage::factory()->create(['name' => 'Initiation Stage']);

        $this->assertDatabaseHas('stages', ['name' => 'Initiation Stage']);
    }

    public function test_status_defaults_to_planned(): void
    {
        $stage = Stage::factory()->create();

        $this->assertEquals(StageStatus::Planned, $stage->status);
    }

    public function test_type_and_status_are_cast_to_enums(): void
    {
        $stage = Stage::factory()->create([
            'type'   => StageType::Initiation,
            'status' => StageStatus::Active,
        ]);

        $fresh = $stage->fresh();
        $this->assertInstanceOf(StageType::class, $fresh->type);
        $this->assertInstanceOf(StageStatus::class, $fresh->status);
    }

    public function test_version_defaults_to_one(): void
    {
        $stage = Stage::factory()->create();

        $this->assertEquals(1, $stage->version);
    }

    public function test_belongs_to_project(): void
    {
        $project = Project::factory()->create();
        $stage   = Stage::factory()->create(['project_id' => $project->id]);

        $this->assertTrue($stage->project->is($project));
    }

    public function test_has_many_boundaries(): void
    {
        $stage = Stage::factory()->create();
        StageBoundary::factory()->count(2)->create(['stage_id' => $stage->id]);

        $this->assertCount(2, $stage->boundaries);
    }

    public function test_created_by_relates_to_person(): void
    {
        $person = Person::factory()->create();
        $stage  = Stage::factory()->create(['created_by' => $person->id]);

        $this->assertTrue($stage->createdBy->is($person));
    }

    public function test_soft_delete_does_not_remove_record(): void
    {
        $stage = Stage::factory()->create();
        $stage->delete();

        $this->assertSoftDeleted('stages', ['id' => $stage->id]);
    }
}
