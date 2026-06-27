<?php

namespace Tests\Feature\Models;

use App\Enums\MeetingActionItemStatus;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingActionItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_action_item(): void
    {
        MeetingActionItem::factory()->create(['description' => 'Follow up with vendor']);

        $this->assertDatabaseHas('meeting_action_items', ['description' => 'Follow up with vendor']);
    }

    public function test_status_defaults_to_open(): void
    {
        $item = MeetingActionItem::factory()->create(['status' => MeetingActionItemStatus::Open->value]);

        $this->assertEquals(MeetingActionItemStatus::Open, $item->status);
    }

    public function test_status_is_cast_to_enum(): void
    {
        $item = MeetingActionItem::factory()->create(['status' => MeetingActionItemStatus::Closed->value]);

        $this->assertInstanceOf(MeetingActionItemStatus::class, $item->fresh()->status);
        $this->assertEquals(MeetingActionItemStatus::Closed, $item->fresh()->status);
    }

    public function test_due_date_is_cast_to_date(): void
    {
        $item = MeetingActionItem::factory()->create(['due_date' => '2026-09-15']);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $item->fresh()->due_date);
    }

    public function test_due_date_is_nullable(): void
    {
        $item = MeetingActionItem::factory()->create(['due_date' => null]);

        $this->assertNull($item->due_date);
    }

    public function test_belongs_to_meeting(): void
    {
        $meeting = Meeting::factory()->create();
        $item    = MeetingActionItem::factory()->create(['meeting_id' => $meeting->id]);

        $this->assertTrue($item->meeting->is($meeting));
    }

    public function test_owner_relates_to_person(): void
    {
        $person = Person::factory()->create();
        $item   = MeetingActionItem::factory()->create(['owner_id' => $person->id]);

        $this->assertTrue($item->owner->is($person));
    }
}
