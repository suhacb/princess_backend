<?php

namespace Tests\Feature\Projects;

use App\Enums\CheckpointReportStatus;
use App\Enums\ProjectRole;
use App\Enums\WorkPackageStatus;
use App\Models\CheckpointReport;
use App\Models\Person;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckpointReportControllerTest extends TestCase
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
        return "/api/projects/{$this->project->id}/checkpoint-reports";
    }

    private function reportUrl(CheckpointReport $report): string
    {
        return "/api/projects/{$this->project->id}/checkpoint-reports/{$report->id}";
    }

    private function makeReport(array $attributes = []): CheckpointReport
    {
        return CheckpointReport::factory()->create(array_merge([
            'project_id' => $this->project->id,
            'ref'        => CheckpointReport::nextRef($this->project->id),
            'created_by' => $this->person->id,
        ], $attributes));
    }

    private function makeWorkPackage(array $attributes = []): WorkPackage
    {
        return WorkPackage::factory()->create(array_merge([
            'project_id'      => $this->project->id,
            'team_manager_id' => $this->person->id,
            'created_by'      => $this->person->id,
        ], $attributes));
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'title'               => 'Sprint 3 checkpoint',
            'period_from'         => '2026-06-01',
            'period_to'           => '2026-06-14',
            'achievements'        => 'Completed login module.',
            'planned_next_period' => 'Start dashboard work.',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_lists_reports(): void
    {
        $this->makeReport();
        $this->makeReport();

        $this->getJson($this->indexUrl())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_status(): void
    {
        $this->makeReport(['status' => CheckpointReportStatus::Draft->value]);
        $this->makeReport(['status' => CheckpointReportStatus::Submitted->value]);

        $this->getJson($this->indexUrl() . '?status=submitted')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_work_package_id(): void
    {
        $wp = $this->makeWorkPackage();
        $this->makeReport(['work_package_id' => $wp->id]);
        $this->makeReport();

        $this->getJson($this->indexUrl() . "?work_package_id={$wp->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_forbidden_for_non_member(): void
    {
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)->getJson($this->indexUrl())->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_report(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload())
            ->assertCreated()
            ->assertJsonPath('data.ref', 'CPR-001')
            ->assertJsonPath('data.status', CheckpointReportStatus::Draft->value)
            ->assertJsonPath('data.title', 'Sprint 3 checkpoint');

        $this->assertDatabaseHas('checkpoint_reports', [
            'project_id' => $this->project->id,
            'ref'        => 'CPR-001',
            'created_by' => $this->person->id,
        ]);
    }

    public function test_store_ref_increments_per_project(): void
    {
        $this->makeReport();

        $this->postJson($this->indexUrl(), $this->storePayload())
            ->assertCreated()
            ->assertJsonPath('data.ref', 'CPR-002');
    }

    public function test_store_with_work_package(): void
    {
        $wp = $this->makeWorkPackage();

        $this->postJson($this->indexUrl(), $this->storePayload(['work_package_id' => $wp->id]))
            ->assertCreated()
            ->assertJsonPath('data.work_package_id', $wp->id);
    }

    public function test_store_requires_title(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['title' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_store_requires_period_from(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['period_from' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period_from');
    }

    public function test_store_requires_period_to(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['period_to' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period_to');
    }

    public function test_store_requires_period_to_after_period_from(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload([
            'period_from' => '2026-06-14',
            'period_to'   => '2026-06-01',
        ]))->assertUnprocessable()->assertJsonValidationErrors('period_to');
    }

    public function test_store_requires_achievements(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['achievements' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('achievements');
    }

    public function test_store_requires_planned_next_period(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['planned_next_period' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('planned_next_period');
    }

    public function test_store_allowed_for_team_manager(): void
    {
        $tmPerson = Person::factory()->create();
        $tmUser   = User::factory()->create(['person_id' => $tmPerson->id]);
        $this->project->members()->create([
            'person_id' => $tmPerson->id,
            'role'      => ProjectRole::TeamManager->value,
        ]);

        $this->actingAs($tmUser)
            ->postJson($this->indexUrl(), $this->storePayload())
            ->assertCreated();
    }

    public function test_store_forbidden_for_observer(): void
    {
        $p = Person::factory()->create();
        $u = User::factory()->create(['person_id' => $p->id]);
        $this->project->members()->create(['person_id' => $p->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($u)->postJson($this->indexUrl(), $this->storePayload())->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_report(): void
    {
        $report = $this->makeReport();

        $this->getJson($this->reportUrl($report))
            ->assertOk()
            ->assertJsonPath('data.id', $report->id);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_edits_draft_report(): void
    {
        $report = $this->makeReport(['title' => 'Original']);

        $this->putJson($this->reportUrl($report), ['title' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated');
    }

    public function test_update_returns_409_on_submitted_report(): void
    {
        $report = $this->makeReport(['status' => CheckpointReportStatus::Submitted->value]);

        $this->putJson($this->reportUrl($report), ['title' => 'Updated'])->assertStatus(409);
    }

    public function test_update_allowed_for_team_manager_of_linked_wp(): void
    {
        $tmPerson = Person::factory()->create();
        $tmUser   = User::factory()->create(['person_id' => $tmPerson->id]);
        $this->project->members()->create([
            'person_id' => $tmPerson->id,
            'role'      => ProjectRole::TeamManager->value,
        ]);
        $wp     = $this->makeWorkPackage(['team_manager_id' => $tmPerson->id]);
        $report = $this->makeReport(['work_package_id' => $wp->id]);

        $this->actingAs($tmUser)
            ->putJson($this->reportUrl($report), ['title' => 'TM update'])
            ->assertOk();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_draft_report(): void
    {
        $report = $this->makeReport(['status' => CheckpointReportStatus::Draft->value]);

        $this->deleteJson($this->reportUrl($report))->assertNoContent();
        $this->assertSoftDeleted('checkpoint_reports', ['id' => $report->id]);
    }

    public function test_destroy_forbidden_on_submitted_report(): void
    {
        $report = $this->makeReport(['status' => CheckpointReportStatus::Submitted->value]);

        $this->deleteJson($this->reportUrl($report))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // submit
    // -------------------------------------------------------------------------

    public function test_submit_transitions_draft_to_submitted(): void
    {
        $report = $this->makeReport(['status' => CheckpointReportStatus::Draft->value]);

        $this->postJson($this->reportUrl($report) . '/submit')
            ->assertOk()
            ->assertJsonPath('data.status', CheckpointReportStatus::Submitted->value);

        $this->assertDatabaseHas('checkpoint_reports', [
            'id'           => $report->id,
            'submitted_by' => $this->person->id,
        ]);
        $this->assertNotNull($report->fresh()->submitted_at);
    }

    public function test_submit_returns_409_if_not_draft(): void
    {
        $report = $this->makeReport(['status' => CheckpointReportStatus::Submitted->value]);

        $this->postJson($this->reportUrl($report) . '/submit')->assertStatus(409);
    }

    public function test_submit_allowed_for_team_manager_of_linked_wp(): void
    {
        $tmPerson = Person::factory()->create();
        $tmUser   = User::factory()->create(['person_id' => $tmPerson->id]);
        $this->project->members()->create([
            'person_id' => $tmPerson->id,
            'role'      => ProjectRole::TeamManager->value,
        ]);
        $wp     = $this->makeWorkPackage(['team_manager_id' => $tmPerson->id]);
        $report = $this->makeReport(['work_package_id' => $wp->id]);

        $this->actingAs($tmUser)
            ->postJson($this->reportUrl($report) . '/submit')
            ->assertOk()
            ->assertJsonPath('data.status', CheckpointReportStatus::Submitted->value);
    }

    // -------------------------------------------------------------------------
    // acknowledge
    // -------------------------------------------------------------------------

    public function test_acknowledge_transitions_submitted_to_acknowledged(): void
    {
        $report = $this->makeReport(['status' => CheckpointReportStatus::Submitted->value]);

        $this->postJson($this->reportUrl($report) . '/acknowledge')
            ->assertOk()
            ->assertJsonPath('data.status', CheckpointReportStatus::Acknowledged->value);

        $this->assertDatabaseHas('checkpoint_reports', [
            'id'              => $report->id,
            'acknowledged_by' => $this->person->id,
        ]);
        $this->assertNotNull($report->fresh()->acknowledged_at);
    }

    public function test_acknowledge_returns_409_if_not_submitted(): void
    {
        $report = $this->makeReport(['status' => CheckpointReportStatus::Draft->value]);

        $this->postJson($this->reportUrl($report) . '/acknowledge')->assertStatus(409);
    }

    public function test_acknowledge_forbidden_for_team_manager(): void
    {
        $tmPerson = Person::factory()->create();
        $tmUser   = User::factory()->create(['person_id' => $tmPerson->id]);
        $this->project->members()->create([
            'person_id' => $tmPerson->id,
            'role'      => ProjectRole::TeamManager->value,
        ]);
        $report = $this->makeReport(['status' => CheckpointReportStatus::Submitted->value]);

        $this->actingAs($tmUser)
            ->postJson($this->reportUrl($report) . '/acknowledge')
            ->assertForbidden();
    }
}
