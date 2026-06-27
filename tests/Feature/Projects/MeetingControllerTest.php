<?php

namespace Tests\Feature\Projects;

use App\Enums\MeetingActionItemStatus;
use App\Enums\ProjectRole;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\Person;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingControllerTest extends TestCase
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

    private function indexUrl(): string
    {
        return "/api/projects/{$this->project->id}/meetings";
    }

    private function meetingUrl(Meeting $meeting): string
    {
        return "/api/projects/{$this->project->id}/meetings/{$meeting->id}";
    }

    private function actionItemsUrl(Meeting $meeting): string
    {
        return "/api/projects/{$this->project->id}/meetings/{$meeting->id}/action-items";
    }

    private function actionItemUrl(Meeting $meeting, MeetingActionItem $item): string
    {
        return "/api/projects/{$this->project->id}/meetings/{$meeting->id}/action-items/{$item->id}";
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title'     => 'Kick-off Meeting',
            'date_time' => '2026-08-01 10:00:00',
        ], $overrides);
    }

    private function makeMeeting(array $attributes = []): Meeting
    {
        return Meeting::factory()->create(array_merge([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ], $attributes));
    }

    private function makeMember(ProjectRole $role = ProjectRole::TeamMember): Person
    {
        $person = Person::factory()->create();
        $this->project->members()->create([
            'person_id' => $person->id,
            'role'      => $role->value,
        ]);
        return $person;
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_lists_meetings_sorted_by_date_desc(): void
    {
        $this->makeMeeting(['date_time' => '2026-07-01 09:00:00']);
        $this->makeMeeting(['date_time' => '2026-09-01 09:00:00']);

        $response = $this->getJson($this->indexUrl())->assertOk();

        $dates = collect($response->json('data'))->pluck('date_time');
        $this->assertTrue($dates->first() > $dates->last());
    }

    public function test_index_includes_action_item_counts(): void
    {
        $meeting = $this->makeMeeting();
        $owner   = $this->makeMember();

        MeetingActionItem::factory()->create(['meeting_id' => $meeting->id, 'owner_id' => $owner->id, 'status' => 'open']);
        MeetingActionItem::factory()->create(['meeting_id' => $meeting->id, 'owner_id' => $owner->id, 'status' => 'open']);
        MeetingActionItem::factory()->create(['meeting_id' => $meeting->id, 'owner_id' => $owner->id, 'status' => 'closed']);

        $response = $this->getJson($this->indexUrl())->assertOk();

        $data = $response->json('data.0');
        $this->assertEquals(2, $data['action_items_open']);
        $this->assertEquals(1, $data['action_items_closed']);
    }

    public function test_index_forbidden_for_non_member(): void
    {
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)->getJson($this->indexUrl())->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_meeting(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.title', 'Kick-off Meeting');

        $this->assertDatabaseHas('meetings', [
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);
    }

    public function test_store_syncs_attendees(): void
    {
        $attendee1 = $this->makeMember();
        $attendee2 = $this->makeMember();

        $response = $this->postJson($this->indexUrl(), $this->validPayload([
            'attendee_ids' => [$attendee1->id, $attendee2->id],
        ]))->assertCreated();

        $this->assertCount(2, $response->json('data.attendees'));
        $this->assertDatabaseHas('meeting_attendees', ['person_id' => $attendee1->id]);
        $this->assertDatabaseHas('meeting_attendees', ['person_id' => $attendee2->id]);
    }

    public function test_store_requires_title(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['title' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_store_requires_date_time(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['date_time' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_time');
    }

    public function test_store_rejects_attendee_not_in_project(): void
    {
        $outsider = Person::factory()->create();

        $this->postJson($this->indexUrl(), $this->validPayload(['attendee_ids' => [$outsider->id]]))
            ->assertUnprocessable();
    }

    public function test_store_forbidden_for_observer(): void
    {
        $observerPerson = $this->makeMember(ProjectRole::Observer);
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);

        $this->actingAs($observer)
            ->postJson($this->indexUrl(), $this->validPayload())
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_meeting_with_attendees_and_action_items(): void
    {
        $meeting = $this->makeMeeting();
        $owner   = $this->makeMember();
        $meeting->attendees()->sync([$owner->id]);
        MeetingActionItem::factory()->create(['meeting_id' => $meeting->id, 'owner_id' => $owner->id]);

        $response = $this->getJson($this->meetingUrl($meeting))->assertOk();

        $this->assertArrayHasKey('attendees', $response->json('data'));
        $this->assertArrayHasKey('action_items', $response->json('data'));
        $this->assertCount(1, $response->json('data.attendees'));
        $this->assertCount(1, $response->json('data.action_items'));
    }

    public function test_show_forbidden_for_non_member(): void
    {
        $meeting  = $this->makeMeeting();
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)->getJson($this->meetingUrl($meeting))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_title(): void
    {
        $meeting = $this->makeMeeting();

        $this->patchJson($this->meetingUrl($meeting), ['title' => 'Revised Title'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Revised Title');

        $this->assertDatabaseHas('meetings', ['id' => $meeting->id, 'updated_by' => $this->person->id]);
    }

    public function test_update_replaces_attendees_when_provided(): void
    {
        $meeting   = $this->makeMeeting();
        $attendee1 = $this->makeMember();
        $attendee2 = $this->makeMember();
        $meeting->attendees()->sync([$attendee1->id]);

        $this->patchJson($this->meetingUrl($meeting), ['attendee_ids' => [$attendee2->id]])->assertOk();

        $this->assertDatabaseMissing('meeting_attendees', ['meeting_id' => $meeting->id, 'person_id' => $attendee1->id]);
        $this->assertDatabaseHas('meeting_attendees', ['meeting_id' => $meeting->id, 'person_id' => $attendee2->id]);
    }

    public function test_update_leaves_attendees_unchanged_when_not_provided(): void
    {
        $meeting  = $this->makeMeeting();
        $attendee = $this->makeMember();
        $meeting->attendees()->sync([$attendee->id]);

        $this->patchJson($this->meetingUrl($meeting), ['title' => 'New Title'])->assertOk();

        $this->assertDatabaseHas('meeting_attendees', ['meeting_id' => $meeting->id, 'person_id' => $attendee->id]);
    }

    public function test_update_forbidden_for_team_member(): void
    {
        $meeting      = $this->makeMeeting();
        $memberPerson = $this->makeMember(ProjectRole::TeamMember);
        $memberUser   = User::factory()->create(['person_id' => $memberPerson->id]);

        $this->actingAs($memberUser)
            ->patchJson($this->meetingUrl($meeting), ['title' => 'Hijacked'])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_soft_deletes_meeting(): void
    {
        $meeting = $this->makeMeeting();

        $this->deleteJson($this->meetingUrl($meeting))->assertNoContent();

        $this->assertSoftDeleted('meetings', ['id' => $meeting->id]);
    }

    public function test_destroy_cascades_action_items(): void
    {
        $meeting = $this->makeMeeting();
        $owner   = $this->makeMember();
        MeetingActionItem::factory()->create(['meeting_id' => $meeting->id, 'owner_id' => $owner->id]);

        $this->deleteJson($this->meetingUrl($meeting))->assertNoContent();

        $this->assertDatabaseMissing('meeting_action_items', ['meeting_id' => $meeting->id]);
    }

    public function test_destroy_forbidden_for_non_member(): void
    {
        $meeting  = $this->makeMeeting();
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)->deleteJson($this->meetingUrl($meeting))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // action items — store
    // -------------------------------------------------------------------------

    public function test_action_item_store_creates_item(): void
    {
        $meeting = $this->makeMeeting();
        $owner   = $this->makeMember();

        $this->postJson($this->actionItemsUrl($meeting), [
            'owner_id'    => $owner->id,
            'description' => 'Follow up with vendor',
        ])
            ->assertCreated()
            ->assertJsonPath('data.description', 'Follow up with vendor')
            ->assertJsonPath('data.status', MeetingActionItemStatus::Open->value);

        $this->assertDatabaseHas('meeting_action_items', [
            'meeting_id'  => $meeting->id,
            'owner_id'    => $owner->id,
            'description' => 'Follow up with vendor',
        ]);
    }

    public function test_action_item_store_requires_owner_id(): void
    {
        $meeting = $this->makeMeeting();

        $this->postJson($this->actionItemsUrl($meeting), ['description' => 'Do something'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('owner_id');
    }

    public function test_action_item_store_rejects_owner_not_in_project(): void
    {
        $meeting  = $this->makeMeeting();
        $outsider = Person::factory()->create();

        $this->postJson($this->actionItemsUrl($meeting), [
            'owner_id'    => $outsider->id,
            'description' => 'Something',
        ])->assertUnprocessable();
    }

    public function test_action_item_store_forbidden_for_team_member(): void
    {
        $meeting      = $this->makeMeeting();
        $memberPerson = $this->makeMember(ProjectRole::TeamMember);
        $memberUser   = User::factory()->create(['person_id' => $memberPerson->id]);

        $this->actingAs($memberUser)
            ->postJson($this->actionItemsUrl($meeting), [
                'owner_id'    => $memberPerson->id,
                'description' => 'Something',
            ])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // action items — update
    // -------------------------------------------------------------------------

    public function test_action_item_update_changes_status(): void
    {
        $meeting = $this->makeMeeting();
        $owner   = $this->makeMember();
        $item    = MeetingActionItem::factory()->create([
            'meeting_id' => $meeting->id,
            'owner_id'   => $owner->id,
            'status'     => MeetingActionItemStatus::Open->value,
        ]);

        $this->patchJson($this->actionItemUrl($meeting, $item), ['status' => MeetingActionItemStatus::Closed->value])
            ->assertOk()
            ->assertJsonPath('data.status', MeetingActionItemStatus::Closed->value);
    }

    public function test_action_item_update_rejects_invalid_status(): void
    {
        $meeting = $this->makeMeeting();
        $owner   = $this->makeMember();
        $item    = MeetingActionItem::factory()->create(['meeting_id' => $meeting->id, 'owner_id' => $owner->id]);

        $this->patchJson($this->actionItemUrl($meeting, $item), ['status' => 'pending'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_action_item_update_forbidden_for_team_member(): void
    {
        $meeting      = $this->makeMeeting();
        $memberPerson = $this->makeMember(ProjectRole::TeamMember);
        $memberUser   = User::factory()->create(['person_id' => $memberPerson->id]);
        $item         = MeetingActionItem::factory()->create([
            'meeting_id' => $meeting->id,
            'owner_id'   => $memberPerson->id,
        ]);

        $this->actingAs($memberUser)
            ->patchJson($this->actionItemUrl($meeting, $item), ['status' => 'closed'])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // action items — destroy
    // -------------------------------------------------------------------------

    public function test_action_item_destroy_removes_item(): void
    {
        $meeting = $this->makeMeeting();
        $owner   = $this->makeMember();
        $item    = MeetingActionItem::factory()->create(['meeting_id' => $meeting->id, 'owner_id' => $owner->id]);

        $this->deleteJson($this->actionItemUrl($meeting, $item))->assertNoContent();

        $this->assertDatabaseMissing('meeting_action_items', ['id' => $item->id]);
    }

    public function test_action_item_destroy_forbidden_for_non_member(): void
    {
        $meeting  = $this->makeMeeting();
        $owner    = $this->makeMember();
        $item     = MeetingActionItem::factory()->create(['meeting_id' => $meeting->id, 'owner_id' => $owner->id]);
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->deleteJson($this->actionItemUrl($meeting, $item))
            ->assertForbidden();
    }
}
