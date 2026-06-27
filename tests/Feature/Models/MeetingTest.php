<?php

namespace Tests\Feature\Models;

use App\Enums\MeetingActionItemStatus;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_meeting(): void
    {
        Meeting::factory()->create(['title' => 'Kick-off']);

        $this->assertDatabaseHas('meetings', ['title' => 'Kick-off']);
    }

    public function test_date_time_is_cast_to_datetime(): void
    {
        $meeting = Meeting::factory()->create(['date_time' => '2026-08-01 10:00:00']);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $meeting->fresh()->date_time);
    }

    public function test_belongs_to_project(): void
    {
        $project = Project::factory()->create();
        $meeting = Meeting::factory()->create(['project_id' => $project->id]);

        $this->assertTrue($meeting->project->is($project));
    }

    public function test_attendees_many_to_many_with_person(): void
    {
        $meeting  = Meeting::factory()->create();
        $person   = Person::factory()->create();
        $meeting->attendees()->attach($person->id);

        $this->assertTrue($meeting->attendees->contains($person));
    }

    public function test_has_many_action_items(): void
    {
        $meeting = Meeting::factory()->create();
        MeetingActionItem::factory()->count(3)->create(['meeting_id' => $meeting->id]);

        $this->assertCount(3, $meeting->actionItems);
    }

    public function test_created_by_relates_to_person(): void
    {
        $person  = Person::factory()->create();
        $meeting = Meeting::factory()->create(['created_by' => $person->id]);

        $this->assertTrue($meeting->createdBy->is($person));
    }

    public function test_updated_by_relates_to_person(): void
    {
        $person  = Person::factory()->create();
        $meeting = Meeting::factory()->create(['updated_by' => $person->id]);

        $this->assertTrue($meeting->updatedBy->is($person));
    }

    public function test_updated_by_is_nullable(): void
    {
        $meeting = Meeting::factory()->create(['updated_by' => null]);

        $this->assertNull($meeting->updatedBy);
    }

    public function test_soft_delete_does_not_remove_record(): void
    {
        $meeting = Meeting::factory()->create();
        $meeting->delete();

        $this->assertSoftDeleted('meetings', ['id' => $meeting->id]);
    }

    public function test_deleting_meeting_cascades_to_action_items(): void
    {
        $meeting = Meeting::factory()->create();
        $item    = MeetingActionItem::factory()->create(['meeting_id' => $meeting->id]);

        $meeting->delete();

        $this->assertDatabaseMissing('meeting_action_items', ['id' => $item->id]);
    }
}
