<?php

namespace Tests\Feature\Models;

use App\Enums\ProjectStatus;
use App\Models\Person;
use App\Models\Project;
use App\Models\Stage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_project(): void
    {
        $project = Project::factory()->create(['name' => 'Core Banking Implementation']);

        $this->assertDatabaseHas('projects', ['name' => 'Core Banking Implementation']);
    }

    public function test_status_defaults_to_pre_project(): void
    {
        $project = Project::factory()->create();

        $this->assertEquals(ProjectStatus::PreProject, $project->status);
    }

    public function test_status_is_cast_to_enum(): void
    {
        $project = Project::factory()->create(['status' => ProjectStatus::Initiation]);

        $this->assertInstanceOf(ProjectStatus::class, $project->fresh()->status);
        $this->assertEquals(ProjectStatus::Initiation, $project->fresh()->status);
    }

    public function test_version_defaults_to_one(): void
    {
        $project = Project::factory()->create();

        $this->assertEquals(1, $project->version);
    }

    public function test_has_many_stages(): void
    {
        $project = Project::factory()->create();
        Stage::factory()->count(3)->create(['project_id' => $project->id]);

        $this->assertCount(3, $project->stages);
    }

    public function test_stages_are_ordered_by_sequence(): void
    {
        $project = Project::factory()->create();
        Stage::factory()->create(['project_id' => $project->id, 'sequence' => 3]);
        Stage::factory()->create(['project_id' => $project->id, 'sequence' => 1]);
        Stage::factory()->create(['project_id' => $project->id, 'sequence' => 2]);

        $sequences = $project->stages->pluck('sequence')->all();
        $this->assertEquals([1, 2, 3], $sequences);
    }

    public function test_created_by_relates_to_person(): void
    {
        $person  = Person::factory()->create();
        $project = Project::factory()->create(['created_by' => $person->id]);

        $this->assertTrue($project->createdBy->is($person));
    }

    public function test_soft_delete_does_not_remove_record(): void
    {
        $project = Project::factory()->create();
        $project->delete();

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }
}
