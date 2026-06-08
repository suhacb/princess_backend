<?php

namespace Tests\Feature\Models;

use App\Enums\BoundaryStatus;
use App\Enums\BoundaryType;
use App\Models\Person;
use App\Models\Stage;
use App\Models\StageBoundary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_boundary(): void
    {
        $boundary = StageBoundary::factory()->create();

        $this->assertDatabaseHas('stage_boundaries', ['id' => $boundary->id]);
    }

    public function test_status_defaults_to_draft(): void
    {
        $boundary = StageBoundary::factory()->create();

        $this->assertEquals(BoundaryStatus::Draft, $boundary->status);
    }

    public function test_type_and_status_are_cast_to_enums(): void
    {
        $boundary = StageBoundary::factory()->create([
            'type'   => BoundaryType::ExceptionReport,
            'status' => BoundaryStatus::Submitted,
        ]);

        $fresh = $boundary->fresh();
        $this->assertInstanceOf(BoundaryType::class, $fresh->type);
        $this->assertInstanceOf(BoundaryStatus::class, $fresh->status);
    }

    public function test_belongs_to_stage(): void
    {
        $stage    = Stage::factory()->create();
        $boundary = StageBoundary::factory()->create(['stage_id' => $stage->id]);

        $this->assertTrue($boundary->stage->is($stage));
    }

    public function test_approved_by_relates_to_person(): void
    {
        $person   = Person::factory()->create();
        $boundary = StageBoundary::factory()->create([
            'approved_by' => $person->id,
            'approved_at' => now(),
            'status'      => BoundaryStatus::Approved,
        ]);

        $this->assertTrue($boundary->approvedBy->is($person));
    }

    public function test_next_stage_relationship(): void
    {
        $stage      = Stage::factory()->create();
        $nextStage  = Stage::factory()->create(['project_id' => $stage->project_id]);
        $boundary   = StageBoundary::factory()->create([
            'stage_id'      => $stage->id,
            'next_stage_id' => $nextStage->id,
        ]);

        $this->assertTrue($boundary->nextStage->is($nextStage));
    }

    public function test_no_soft_delete(): void
    {
        $this->assertFalse(in_array(
            \Illuminate\Database\Eloquent\SoftDeletes::class,
            class_uses_recursive(StageBoundary::class)
        ));
    }
}
