<?php

namespace Tests\Feature\Projects;

use App\Enums\ChangeRequestType;
use App\Enums\ChangeStatus;
use App\Enums\ProjectRole;
use App\Models\Change;
use App\Models\Issue;
use App\Models\Person;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangeLogControllerTest extends TestCase
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
        return "/api/projects/{$this->project->id}/changes";
    }

    private function changeUrl(Change $change): string
    {
        return "/api/projects/{$this->project->id}/changes/{$change->id}";
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'request_type'     => ChangeRequestType::Rfc->value,
            'title'            => 'Extend phase 2 deadline',
            'description'      => 'Phase 2 deliverables need two extra weeks.',
            'impact_assessment'=> 'Minor cost overrun, no quality impact.',
        ], $overrides);
    }

    private function makeChange(array $overrides = []): Change
    {
        return Change::factory()->create(array_merge([
            'project_id'   => $this->project->id,
            'raised_by'    => $this->person->id,
            'status'       => ChangeStatus::Proposed->value,
        ], $overrides));
    }

    public function test_index_lists_changes(): void
    {
        $this->makeChange();
        $this->makeChange();

        $this->getJson($this->indexUrl())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_forbidden_for_non_member(): void
    {
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->indexUrl())
            ->assertForbidden();
    }

    public function test_store_creates_change(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.title', 'Extend phase 2 deadline')
            ->assertJsonPath('data.status', ChangeStatus::Proposed->value);

        $this->assertDatabaseHas('changes', [
            'project_id' => $this->project->id,
            'raised_by'  => $this->person->id,
            'title'      => 'Extend phase 2 deadline',
        ]);
    }

    public function test_store_requires_request_type(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['request_type' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('request_type');
    }

    public function test_store_rejects_invalid_request_type(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['request_type' => 'not-a-type']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('request_type');
    }

    public function test_store_requires_title(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['title' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_store_rejects_title_over_max_length(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['title' => str_repeat('a', 256)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_store_rejects_non_string_description(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['description' => ['not-a-string']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('description');
    }

    public function test_store_rejects_non_string_impact_assessment(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['impact_assessment' => ['not-a-string']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('impact_assessment');
    }

    public function test_store_rejects_priority_over_max_length(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['priority' => str_repeat('a', 51)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('priority');
    }

    public function test_store_accepts_valid_priority(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['priority' => 'High']))
            ->assertCreated()
            ->assertJsonPath('data.priority', 'High');
    }

    public function test_store_rejects_non_integer_issue_id(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['issue_id' => 'not-an-id']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('issue_id');
    }

    public function test_store_rejects_nonexistent_issue_id(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['issue_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('issue_id');
    }

    public function test_store_accepts_existing_issue_id(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'raised_by'  => $this->person->id,
        ]);

        $this->postJson($this->indexUrl(), $this->validPayload(['issue_id' => $issue->id]))
            ->assertCreated()
            ->assertJsonPath('data.issue_id', $issue->id);
    }

    public function test_store_rejects_invalid_implementation_due(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['implementation_due' => 'not-a-date']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('implementation_due');
    }

    public function test_store_accepts_valid_implementation_due(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['implementation_due' => '2026-08-01']))
            ->assertCreated()
            ->assertJsonPath('data.implementation_due', '2026-08-01');
    }

    public function test_store_forbidden_for_observer(): void
    {
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create([
            'person_id' => $observerPerson->id,
            'role'      => ProjectRole::Observer->value,
        ]);

        $this->actingAs($observer)
            ->postJson($this->indexUrl(), $this->validPayload())
            ->assertForbidden();
    }

    public function test_show_returns_change(): void
    {
        $change = $this->makeChange();

        $this->getJson($this->changeUrl($change))
            ->assertOk()
            ->assertJsonPath('data.id', $change->id);
    }

    public function test_update_edits_change(): void
    {
        $change = $this->makeChange();

        $this->putJson($this->changeUrl($change), ['title' => 'Updated title'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title');
    }

    public function test_update_rejects_empty_request_type(): void
    {
        $change = $this->makeChange();

        $this->putJson($this->changeUrl($change), ['request_type' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('request_type');
    }

    public function test_update_rejects_invalid_request_type(): void
    {
        $change = $this->makeChange();

        $this->putJson($this->changeUrl($change), ['request_type' => 'not-a-type'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('request_type');
    }

    public function test_update_accepts_valid_request_type(): void
    {
        $change = $this->makeChange();

        $this->putJson($this->changeUrl($change), ['request_type' => ChangeRequestType::OffSpec->value])
            ->assertOk()
            ->assertJsonPath('data.request_type', ChangeRequestType::OffSpec->value);
    }

    public function test_update_rejects_empty_title(): void
    {
        $change = $this->makeChange();

        $this->putJson($this->changeUrl($change), ['title' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_update_rejects_title_over_max_length(): void
    {
        $change = $this->makeChange();

        $this->putJson($this->changeUrl($change), ['title' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_update_rejects_non_string_description(): void
    {
        $change = $this->makeChange();

        $this->putJson($this->changeUrl($change), ['description' => ['not-a-string']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('description');
    }

    public function test_update_rejects_non_string_impact_assessment(): void
    {
        $change = $this->makeChange();

        $this->putJson($this->changeUrl($change), ['impact_assessment' => ['not-a-string']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('impact_assessment');
    }

    public function test_update_rejects_priority_over_max_length(): void
    {
        $change = $this->makeChange();

        $this->putJson($this->changeUrl($change), ['priority' => str_repeat('a', 51)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('priority');
    }

    public function test_update_accepts_valid_priority(): void
    {
        $change = $this->makeChange();

        $this->putJson($this->changeUrl($change), ['priority' => 'Low'])
            ->assertOk()
            ->assertJsonPath('data.priority', 'Low');
    }

    public function test_update_rejects_empty_status(): void
    {
        $change = $this->makeChange();

        $this->putJson($this->changeUrl($change), ['status' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_update_rejects_invalid_status(): void
    {
        $change = $this->makeChange();

        $this->putJson($this->changeUrl($change), ['status' => 'not-a-status'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_update_rejects_non_integer_issue_id(): void
    {
        $change = $this->makeChange();

        $this->putJson($this->changeUrl($change), ['issue_id' => 'not-an-id'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('issue_id');
    }

    public function test_update_rejects_nonexistent_issue_id(): void
    {
        $change = $this->makeChange();

        $this->putJson($this->changeUrl($change), ['issue_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('issue_id');
    }

    public function test_update_accepts_existing_issue_id(): void
    {
        $change = $this->makeChange();
        $issue  = Issue::factory()->create([
            'project_id' => $this->project->id,
            'raised_by'  => $this->person->id,
        ]);

        $this->putJson($this->changeUrl($change), ['issue_id' => $issue->id])
            ->assertOk()
            ->assertJsonPath('data.issue_id', $issue->id);
    }

    public function test_update_rejects_invalid_implementation_due(): void
    {
        $change = $this->makeChange();

        $this->putJson($this->changeUrl($change), ['implementation_due' => 'not-a-date'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('implementation_due');
    }

    public function test_update_accepts_valid_implementation_due(): void
    {
        $change = $this->makeChange();

        $this->putJson($this->changeUrl($change), ['implementation_due' => '2026-08-01'])
            ->assertOk()
            ->assertJsonPath('data.implementation_due', '2026-08-01');
    }

    public function test_update_rejects_invalid_implemented_at(): void
    {
        $change = $this->makeChange();

        $this->putJson($this->changeUrl($change), ['implemented_at' => 'not-a-date'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('implemented_at');
    }

    public function test_update_accepts_valid_implemented_at(): void
    {
        $change = $this->makeChange();

        $this->putJson($this->changeUrl($change), ['implemented_at' => '2026-07-01'])
            ->assertOk()
            ->assertJsonPath('data.implemented_at', '2026-07-01');
    }

    public function test_destroy_deletes_change(): void
    {
        $change = $this->makeChange();

        $this->deleteJson($this->changeUrl($change))->assertNoContent();

        $this->assertDatabaseMissing('changes', ['id' => $change->id]);
    }

    public function test_executive_can_approve_change(): void
    {
        $execPerson = Person::factory()->create();
        $exec       = User::factory()->create(['person_id' => $execPerson->id]);
        $this->project->members()->create([
            'person_id' => $execPerson->id,
            'role'      => ProjectRole::Executive->value,
        ]);
        $change = $this->makeChange();

        $this->actingAs($exec)
            ->patchJson($this->changeUrl($change) . '/approve', [
                'decision_rationale' => 'Schedule flexibility exists.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ChangeStatus::Approved->value);

        $this->assertDatabaseHas('changes', [
            'id'             => $change->id,
            'status'         => ChangeStatus::Approved->value,
            'decision_by'    => $execPerson->id,
        ]);
    }

    public function test_executive_can_reject_change(): void
    {
        $execPerson = Person::factory()->create();
        $exec       = User::factory()->create(['person_id' => $execPerson->id]);
        $this->project->members()->create([
            'person_id' => $execPerson->id,
            'role'      => ProjectRole::Executive->value,
        ]);
        $change = $this->makeChange();

        $this->actingAs($exec)
            ->patchJson($this->changeUrl($change) . '/reject')
            ->assertOk()
            ->assertJsonPath('data.status', ChangeStatus::Rejected->value);
    }

    public function test_approve_returns_409_when_not_decidable(): void
    {
        $execPerson = Person::factory()->create();
        $exec       = User::factory()->create(['person_id' => $execPerson->id]);
        $this->project->members()->create([
            'person_id' => $execPerson->id,
            'role'      => ProjectRole::Executive->value,
        ]);
        $change = $this->makeChange(['status' => ChangeStatus::Implemented->value]);

        $this->actingAs($exec)
            ->patchJson($this->changeUrl($change) . '/approve')
            ->assertStatus(409);
    }

    public function test_approve_forbidden_for_project_manager(): void
    {
        $change = $this->makeChange();

        $this->patchJson($this->changeUrl($change) . '/approve')
            ->assertForbidden();
    }

    public function test_change_authority_can_approve_minor_change(): void
    {
        $caPerson = Person::factory()->create();
        $ca       = User::factory()->create(['person_id' => $caPerson->id]);
        $this->project->members()->create([
            'person_id' => $caPerson->id,
            'role'      => ProjectRole::ChangeAuthority->value,
        ]);
        $change = $this->makeChange();

        $this->actingAs($ca)
            ->patchJson($this->changeUrl($change) . '/approve')
            ->assertOk()
            ->assertJsonPath('data.status', ChangeStatus::Approved->value);
    }
}
