<?php

namespace Tests\Feature\Projects;

use App\Enums\ChangeRequestType;
use App\Enums\ChangeStatus;
use App\Enums\ProjectRole;
use App\Models\Change;
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
