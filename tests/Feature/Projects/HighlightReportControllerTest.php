<?php

namespace Tests\Feature\Projects;

use App\Enums\HighlightReportStatus;
use App\Enums\ProjectRole;
use App\Models\HighlightReport;
use App\Models\Person;
use App\Models\Project;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HighlightReportControllerTest extends TestCase
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
        return "/api/projects/{$this->project->id}/highlight-reports";
    }

    private function reportUrl(HighlightReport $report): string
    {
        return "/api/projects/{$this->project->id}/highlight-reports/{$report->id}";
    }

    private function makeReport(array $attributes = []): HighlightReport
    {
        return HighlightReport::factory()->create(array_merge([
            'project_id' => $this->project->id,
            'ref'        => HighlightReport::nextRef($this->project->id),
            'created_by' => $this->person->id,
        ], $attributes));
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'title'             => 'June Highlight Report',
            'period_from'       => '2026-06-01',
            'period_to'         => '2026-06-30',
            'this_period_work'  => 'Completed authentication module.',
            'next_period_work'  => 'Start dashboard integration.',
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
        $this->makeReport(['status' => HighlightReportStatus::Draft->value]);
        $this->makeReport(['status' => HighlightReportStatus::Submitted->value]);

        $this->getJson($this->indexUrl() . '?status=submitted')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_stage_id(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->person->id]);
        $this->makeReport(['stage_id' => $stage->id]);
        $this->makeReport();

        $this->getJson($this->indexUrl() . "?stage_id={$stage->id}")
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
            ->assertJsonPath('data.ref', 'HLR-001')
            ->assertJsonPath('data.status', HighlightReportStatus::Draft->value)
            ->assertJsonPath('data.title', 'June Highlight Report');

        $this->assertDatabaseHas('highlight_reports', [
            'project_id' => $this->project->id,
            'ref'        => 'HLR-001',
            'created_by' => $this->person->id,
        ]);
    }

    public function test_store_ref_increments_per_project(): void
    {
        $this->makeReport();

        $this->postJson($this->indexUrl(), $this->storePayload())
            ->assertCreated()
            ->assertJsonPath('data.ref', 'HLR-002');
    }

    public function test_store_accepts_valid_rag_statuses(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload([
            'budget_status'   => 'amber',
            'schedule_status' => 'red',
        ]))->assertCreated()
            ->assertJsonPath('data.budget_status', 'amber')
            ->assertJsonPath('data.schedule_status', 'red');
    }

    public function test_store_rejects_invalid_budget_status(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['budget_status' => 'yellow']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('budget_status');
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

    public function test_store_requires_period_to_after_period_from(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload([
            'period_from' => '2026-06-30',
            'period_to'   => '2026-06-01',
        ]))->assertUnprocessable()->assertJsonValidationErrors('period_to');
    }

    public function test_store_requires_this_period_work(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['this_period_work' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('this_period_work');
    }

    public function test_store_requires_next_period_work(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['next_period_work' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('next_period_work');
    }

    public function test_store_fails_when_stage_id_does_not_exist(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['stage_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stage_id');
    }

    public function test_store_creates_report_with_valid_stage_id(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->person->id]);

        $this->postJson($this->indexUrl(), $this->storePayload(['stage_id' => $stage->id]))
            ->assertCreated()
            ->assertJsonPath('data.stage_id', $stage->id);
    }

    public function test_store_fails_when_title_too_long(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['title' => str_repeat('a', 256)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_store_fails_when_period_from_invalid(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['period_from' => 'not-a-date']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period_from');
    }

    public function test_store_requires_period_to(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['period_to' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period_to');
    }

    public function test_store_fails_when_schedule_status_invalid(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['schedule_status' => 'blue']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('schedule_status');
    }

    public function test_store_fails_when_forecast_finish_invalid(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['forecast_finish' => 'not-a-date']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('forecast_finish');
    }

    public function test_store_creates_report_with_valid_forecast_finish(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['forecast_finish' => '2026-09-01']))
            ->assertCreated()
            ->assertJsonPath('data.forecast_finish', '2026-09-01');
    }

    public function test_store_forbidden_for_team_manager(): void
    {
        $p = Person::factory()->create();
        $u = User::factory()->create(['person_id' => $p->id]);
        $this->project->members()->create(['person_id' => $p->id, 'role' => ProjectRole::TeamManager->value]);

        $this->actingAs($u)->postJson($this->indexUrl(), $this->storePayload())->assertForbidden();
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

    public function test_update_fails_when_title_empty(): void
    {
        $report = $this->makeReport(['title' => 'Original']);

        $this->putJson($this->reportUrl($report), ['title' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_update_fails_when_period_from_invalid(): void
    {
        $report = $this->makeReport();

        $this->putJson($this->reportUrl($report), ['period_from' => 'not-a-date'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period_from');
    }

    public function test_update_fails_when_period_to_before_period_from(): void
    {
        $report = $this->makeReport();

        $this->putJson($this->reportUrl($report), [
            'period_from' => '2026-06-30',
            'period_to'   => '2026-06-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period_to');
    }

    public function test_update_fails_when_this_period_work_empty(): void
    {
        $report = $this->makeReport();

        $this->putJson($this->reportUrl($report), ['this_period_work' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('this_period_work');
    }

    public function test_update_fails_when_next_period_work_empty(): void
    {
        $report = $this->makeReport();

        $this->putJson($this->reportUrl($report), ['next_period_work' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('next_period_work');
    }

    public function test_update_fails_when_budget_status_invalid(): void
    {
        $report = $this->makeReport();

        $this->putJson($this->reportUrl($report), ['budget_status' => 'blue'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('budget_status');
    }

    public function test_update_fails_when_schedule_status_invalid(): void
    {
        $report = $this->makeReport();

        $this->putJson($this->reportUrl($report), ['schedule_status' => 'blue'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('schedule_status');
    }

    public function test_update_fails_when_stage_id_does_not_exist(): void
    {
        $report = $this->makeReport();

        $this->putJson($this->reportUrl($report), ['stage_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stage_id');
    }

    public function test_update_edits_stage_id(): void
    {
        $report = $this->makeReport();
        $stage  = Stage::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->person->id]);

        $this->putJson($this->reportUrl($report), ['stage_id' => $stage->id])
            ->assertOk()
            ->assertJsonPath('data.stage_id', $stage->id);
    }

    public function test_update_returns_409_on_submitted_report(): void
    {
        $report = $this->makeReport(['status' => HighlightReportStatus::Submitted->value]);

        $this->putJson($this->reportUrl($report), ['title' => 'Updated'])->assertStatus(409);
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_draft_report(): void
    {
        $report = $this->makeReport(['status' => HighlightReportStatus::Draft->value]);

        $this->deleteJson($this->reportUrl($report))->assertNoContent();
        $this->assertSoftDeleted('highlight_reports', ['id' => $report->id]);
    }

    public function test_destroy_forbidden_on_submitted_report(): void
    {
        $report = $this->makeReport(['status' => HighlightReportStatus::Submitted->value]);

        $this->deleteJson($this->reportUrl($report))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // submit
    // -------------------------------------------------------------------------

    public function test_submit_transitions_draft_to_submitted(): void
    {
        $report = $this->makeReport(['status' => HighlightReportStatus::Draft->value]);

        $this->postJson($this->reportUrl($report) . '/submit')
            ->assertOk()
            ->assertJsonPath('data.status', HighlightReportStatus::Submitted->value);

        $this->assertDatabaseHas('highlight_reports', [
            'id'           => $report->id,
            'submitted_by' => $this->person->id,
        ]);
        $this->assertNotNull($report->fresh()->submitted_at);
    }

    public function test_submit_returns_409_if_not_draft(): void
    {
        $report = $this->makeReport(['status' => HighlightReportStatus::Submitted->value]);

        $this->postJson($this->reportUrl($report) . '/submit')->assertStatus(409);
    }

    public function test_submit_forbidden_for_team_manager(): void
    {
        $p = Person::factory()->create();
        $u = User::factory()->create(['person_id' => $p->id]);
        $this->project->members()->create(['person_id' => $p->id, 'role' => ProjectRole::TeamManager->value]);
        $report = $this->makeReport();

        $this->actingAs($u)->postJson($this->reportUrl($report) . '/submit')->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // approve
    // -------------------------------------------------------------------------

    public function test_approve_transitions_submitted_to_approved(): void
    {
        $executive = Person::factory()->create();
        $execUser  = User::factory()->create(['person_id' => $executive->id]);
        $this->project->members()->create([
            'person_id' => $executive->id,
            'role'      => ProjectRole::Executive->value,
        ]);
        $report = $this->makeReport(['status' => HighlightReportStatus::Submitted->value]);

        $this->actingAs($execUser)
            ->postJson($this->reportUrl($report) . '/approve')
            ->assertOk()
            ->assertJsonPath('data.status', HighlightReportStatus::Approved->value);

        $this->assertDatabaseHas('highlight_reports', [
            'id'          => $report->id,
            'approved_by' => $executive->id,
        ]);
        $this->assertNotNull($report->fresh()->approved_at);
    }

    public function test_approve_returns_409_if_not_submitted(): void
    {
        $executive = Person::factory()->create();
        $execUser  = User::factory()->create(['person_id' => $executive->id]);
        $this->project->members()->create([
            'person_id' => $executive->id,
            'role'      => ProjectRole::Executive->value,
        ]);
        $report = $this->makeReport(['status' => HighlightReportStatus::Draft->value]);

        $this->actingAs($execUser)
            ->postJson($this->reportUrl($report) . '/approve')
            ->assertStatus(409);
    }

    public function test_approve_forbidden_for_pm(): void
    {
        $report = $this->makeReport(['status' => HighlightReportStatus::Submitted->value]);

        $this->postJson($this->reportUrl($report) . '/approve')->assertForbidden();
    }

    public function test_approve_allowed_for_senior_user(): void
    {
        $su     = Person::factory()->create();
        $suUser = User::factory()->create(['person_id' => $su->id]);
        $this->project->members()->create([
            'person_id' => $su->id,
            'role'      => ProjectRole::SeniorUser->value,
        ]);
        $report = $this->makeReport(['status' => HighlightReportStatus::Submitted->value]);

        $this->actingAs($suUser)
            ->postJson($this->reportUrl($report) . '/approve')
            ->assertOk()
            ->assertJsonPath('data.status', HighlightReportStatus::Approved->value);
    }
}
