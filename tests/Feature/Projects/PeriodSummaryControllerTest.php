<?php

namespace Tests\Feature\Projects;

use App\Enums\ChangeStatus;
use App\Enums\IssueStatus;
use App\Enums\ProjectRole;
use App\Enums\QaDocumentStatus;
use App\Enums\TestSessionStatus;
use App\Enums\WorkPackageStatus;
use App\Models\AcceptanceCriterion;
use App\Models\Change;
use App\Models\DailyLogEntry;
use App\Models\Issue;
use App\Models\Lesson;
use App\Models\Person;
use App\Models\Project;
use App\Models\QaDocument;
use App\Models\Risk;
use App\Models\TestSession;
use App\Models\User;
use App\Models\WorkPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodSummaryControllerTest extends TestCase
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

    private function url(array $params = []): string
    {
        $query = http_build_query(array_merge(['from' => '2026-06-01', 'to' => '2026-06-30'], $params));
        return "/api/projects/{$this->project->id}/period-summary?{$query}";
    }

    // -------------------------------------------------------------------------
    // validation
    // -------------------------------------------------------------------------

    public function test_requires_from(): void
    {
        $this->getJson("/api/projects/{$this->project->id}/period-summary?to=2026-06-30")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from');
    }

    public function test_requires_to(): void
    {
        $this->getJson("/api/projects/{$this->project->id}/period-summary?from=2026-06-01")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_to_must_not_be_before_from(): void
    {
        $this->getJson($this->url(['from' => '2026-06-30', 'to' => '2026-06-01']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    // -------------------------------------------------------------------------
    // response structure
    // -------------------------------------------------------------------------

    public function test_returns_expected_structure(): void
    {
        $response = $this->getJson($this->url())->assertOk();

        $response->assertJsonStructure([
            'data' => [
                'period'             => ['from', 'to', 'stage_id'],
                'work_packages'      => ['authorized', 'completed', 'in_progress', 'overdue'],
                'issues'             => ['raised', 'closed', 'escalated', 'open_count'],
                'risks'              => ['new', 'updated', 'open_count'],
                'changes'            => ['submitted', 'approved', 'rejected'],
                'quality'            => ['qa_documents_confirmed', 'test_sessions_completed', 'acceptance_criteria_accepted'],
                'lessons'            => ['added'],
                'daily_log_entries'  => ['count'],
            ],
        ]);
    }

    public function test_returns_zeros_when_no_activity(): void
    {
        $data = $this->getJson($this->url())->assertOk()->json('data');

        $this->assertEquals(0, $data['work_packages']['authorized']);
        $this->assertEquals(0, $data['issues']['raised']);
        $this->assertEquals(0, $data['lessons']['added']);
    }

    // -------------------------------------------------------------------------
    // work packages
    // -------------------------------------------------------------------------

    public function test_counts_completed_work_packages_in_period(): void
    {
        WorkPackage::factory()->create([
            'project_id'      => $this->project->id,
            'team_manager_id' => $this->person->id,
            'created_by'      => $this->person->id,
            'status'          => WorkPackageStatus::Completed->value,
            'planned_start'   => '2026-05-01',
            'planned_end'     => '2026-06-15',
            'actual_end'      => '2026-06-10',
        ]);

        $data = $this->getJson($this->url())->assertOk()->json('data');

        $this->assertEquals(1, $data['work_packages']['completed']);
    }

    public function test_does_not_count_completed_wp_outside_period(): void
    {
        WorkPackage::factory()->create([
            'project_id'      => $this->project->id,
            'team_manager_id' => $this->person->id,
            'created_by'      => $this->person->id,
            'status'          => WorkPackageStatus::Completed->value,
            'planned_start'   => '2026-03-01',
            'planned_end'     => '2026-04-30',
            'actual_end'      => '2026-05-05',
        ]);

        $data = $this->getJson($this->url())->assertOk()->json('data');

        $this->assertEquals(0, $data['work_packages']['completed']);
    }

    // -------------------------------------------------------------------------
    // issues
    // -------------------------------------------------------------------------

    public function test_counts_issues_raised_in_period(): void
    {
        Issue::factory()->create([
            'project_id' => $this->project->id,
            'raised_by'  => $this->person->id,
            'raised_at'  => '2026-06-15 10:00:00',
        ]);

        $data = $this->getJson($this->url())->assertOk()->json('data');

        $this->assertEquals(1, $data['issues']['raised']);
    }

    public function test_counts_closed_issues_in_period(): void
    {
        Issue::factory()->create([
            'project_id'  => $this->project->id,
            'raised_by'   => $this->person->id,
            'raised_at'   => '2026-05-01 00:00:00',
            'status'      => IssueStatus::Closed->value,
            'resolved_at' => '2026-06-20 00:00:00',
            'resolution'  => 'Fixed.',
        ]);

        $data = $this->getJson($this->url())->assertOk()->json('data');

        $this->assertEquals(1, $data['issues']['closed']);
    }

    // -------------------------------------------------------------------------
    // lessons
    // -------------------------------------------------------------------------

    public function test_counts_lessons_added_in_period(): void
    {
        Lesson::factory()->create([
            'project_id' => $this->project->id,
            'raised_by'  => $this->person->id,
            'created_at' => '2026-06-10 00:00:00',
        ]);

        $data = $this->getJson($this->url())->assertOk()->json('data');

        $this->assertEquals(1, $data['lessons']['added']);
    }

    // -------------------------------------------------------------------------
    // daily log
    // -------------------------------------------------------------------------

    public function test_counts_daily_log_entries_in_period(): void
    {
        DailyLogEntry::factory()->create([
            'project_id' => $this->project->id,
            'author_id'  => $this->person->id,
            'created_at' => '2026-06-05 00:00:00',
        ]);

        $data = $this->getJson($this->url())->assertOk()->json('data');

        $this->assertEquals(1, $data['daily_log_entries']['count']);
    }

    // -------------------------------------------------------------------------
    // authorization
    // -------------------------------------------------------------------------

    public function test_forbidden_for_non_member(): void
    {
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)->getJson($this->url())->assertForbidden();
    }

    public function test_accessible_for_observer(): void
    {
        $p = Person::factory()->create();
        $u = User::factory()->create(['person_id' => $p->id]);
        $this->project->members()->create(['person_id' => $p->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($u)->getJson($this->url())->assertOk();
    }
}
