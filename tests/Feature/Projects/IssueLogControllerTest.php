<?php

namespace Tests\Feature\Projects;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\ProjectRole;
use App\Models\Issue;
use App\Models\Person;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueLogControllerTest extends TestCase
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
        return "/api/projects/{$this->project->id}/issues";
    }

    private function issueUrl(Issue $issue): string
    {
        return "/api/projects/{$this->project->id}/issues/{$issue->id}";
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'issue_type' => IssueType::Problem->value,
            'title'      => 'Delivery milestone missed',
            'priority'   => IssuePriority::High->value,
        ], $overrides);
    }

    private function makeIssue(array $overrides = []): Issue
    {
        return Issue::factory()->create(array_merge([
            'project_id' => $this->project->id,
            'raised_by'  => $this->person->id,
            'status'     => IssueStatus::Open->value,
        ], $overrides));
    }

    public function test_index_lists_issues(): void
    {
        $this->makeIssue();
        $this->makeIssue();

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

    public function test_store_creates_issue(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.title', 'Delivery milestone missed')
            ->assertJsonPath('data.status', IssueStatus::Open->value);

        $this->assertDatabaseHas('issues', [
            'project_id' => $this->project->id,
            'raised_by'  => $this->person->id,
            'title'      => 'Delivery milestone missed',
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

    public function test_show_returns_issue(): void
    {
        $issue = $this->makeIssue();

        $this->getJson($this->issueUrl($issue))
            ->assertOk()
            ->assertJsonPath('data.id', $issue->id);
    }

    public function test_update_edits_issue(): void
    {
        $issue = $this->makeIssue();

        $this->putJson($this->issueUrl($issue), ['title' => 'Updated title'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title');
    }

    public function test_destroy_deletes_issue(): void
    {
        $issue = $this->makeIssue();

        $this->deleteJson($this->issueUrl($issue))->assertNoContent();

        $this->assertDatabaseMissing('issues', ['id' => $issue->id]);
    }

    public function test_escalate_sets_status_and_timestamps(): void
    {
        $issue = $this->makeIssue();

        $this->postJson($this->issueUrl($issue) . '/escalate', [
            'escalation_reason' => 'Exceeds project tolerance.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', IssueStatus::Escalated->value);

        $this->assertDatabaseHas('issues', [
            'id'                => $issue->id,
            'status'            => IssueStatus::Escalated->value,
            'escalation_reason' => 'Exceeds project tolerance.',
        ]);
    }

    public function test_escalate_requires_open_or_under_review_status(): void
    {
        $issue = $this->makeIssue(['status' => IssueStatus::Closed->value]);

        $this->postJson($this->issueUrl($issue) . '/escalate', [
            'escalation_reason' => 'Exceeds project tolerance.',
        ])
            ->assertStatus(409);
    }

    public function test_escalate_forbidden_without_permission(): void
    {
        $memberPerson = Person::factory()->create();
        $member       = User::factory()->create(['person_id' => $memberPerson->id]);
        $this->project->members()->create([
            'person_id' => $memberPerson->id,
            'role'      => ProjectRole::Observer->value,
        ]);
        $issue = $this->makeIssue();

        $this->actingAs($member)
            ->postJson($this->issueUrl($issue) . '/escalate', [
                'escalation_reason' => 'Exceeds tolerance.',
            ])
            ->assertForbidden();
    }

    public function test_resolve_closes_issue_with_resolution(): void
    {
        $issue = $this->makeIssue();

        $this->postJson($this->issueUrl($issue) . '/resolve', [
            'resolution' => 'Replanned delivery with supplier.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', IssueStatus::Closed->value);

        $this->assertDatabaseHas('issues', [
            'id'         => $issue->id,
            'status'     => IssueStatus::Closed->value,
            'resolution' => 'Replanned delivery with supplier.',
        ]);
    }

    public function test_resolve_returns_409_when_already_closed(): void
    {
        $issue = $this->makeIssue(['status' => IssueStatus::Closed->value]);

        $this->postJson($this->issueUrl($issue) . '/resolve', [
            'resolution' => 'Already resolved.',
        ])
            ->assertStatus(409);
    }
}
