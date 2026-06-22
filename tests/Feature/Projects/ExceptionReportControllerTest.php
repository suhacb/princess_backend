<?php

namespace Tests\Feature\Projects;

use App\Enums\ExceptionReportStatus;
use App\Enums\ExceptionTriggerType;
use App\Enums\ProjectRole;
use App\Enums\WorkPackageStatus;
use App\Models\ExceptionReport;
use App\Models\Person;
use App\Models\Project;
use App\Models\Stage;
use App\Models\User;
use App\Models\WorkPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExceptionReportControllerTest extends TestCase
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
        return "/api/projects/{$this->project->id}/exception-reports";
    }

    private function reportUrl(ExceptionReport $report): string
    {
        return "/api/projects/{$this->project->id}/exception-reports/{$report->id}";
    }

    private function makeReport(array $attributes = []): ExceptionReport
    {
        return ExceptionReport::factory()->create(array_merge([
            'project_id' => $this->project->id,
            'ref'        => ExceptionReport::nextRef($this->project->id),
            'created_by' => $this->person->id,
        ], $attributes));
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'title'          => 'Exception: login module delivery',
            'trigger_type'   => ExceptionTriggerType::ToleranceTime->value,
            'description'    => 'Delivery will be 2 weeks late.',
            'cause'          => 'Scope increased without replanning.',
            'impact'         => 'Stage end date will be missed.',
            'recommendation' => 'Raise exception plan to extend stage.',
        ], $overrides);
    }

    private function makeBoardUser(ProjectRole $role = ProjectRole::Executive): array
    {
        $p = Person::factory()->create();
        $u = User::factory()->create(['person_id' => $p->id]);
        $this->project->members()->create(['person_id' => $p->id, 'role' => $role->value]);
        return [$p, $u];
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
        $this->makeReport(['status' => ExceptionReportStatus::Draft->value]);
        $this->makeReport(['status' => ExceptionReportStatus::Submitted->value]);

        $this->getJson($this->indexUrl() . '?status=submitted')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_trigger_type(): void
    {
        $this->makeReport(['trigger_type' => ExceptionTriggerType::ToleranceTime->value]);
        $this->makeReport(['trigger_type' => ExceptionTriggerType::Manual->value]);

        $this->getJson($this->indexUrl() . '?trigger_type=manual')
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
            ->assertJsonPath('data.ref', 'EXR-001')
            ->assertJsonPath('data.status', ExceptionReportStatus::Draft->value)
            ->assertJsonPath('data.trigger_type', ExceptionTriggerType::ToleranceTime->value);

        $this->assertDatabaseHas('exception_reports', [
            'project_id' => $this->project->id,
            'ref'        => 'EXR-001',
            'created_by' => $this->person->id,
        ]);
    }

    public function test_store_ref_increments_per_project(): void
    {
        $this->makeReport();

        $this->postJson($this->indexUrl(), $this->storePayload())
            ->assertCreated()
            ->assertJsonPath('data.ref', 'EXR-002');
    }

    public function test_store_with_options_array(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload([
            'options' => [
                ['title' => 'Option A', 'description' => 'Extend timeline by 2 weeks.', 'pros' => 'Feasible.', 'cons' => 'Delays release.'],
                ['title' => 'Option B', 'description' => 'Reduce scope.'],
            ],
        ]))->assertCreated()
            ->assertJsonPath('data.options.0.title', 'Option A')
            ->assertJsonPath('data.options.1.title', 'Option B');
    }

    public function test_store_rejects_option_missing_title(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload([
            'options' => [['description' => 'No title here.']],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('options.0.title');
    }

    public function test_store_rejects_option_missing_description(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload([
            'options' => [['title' => 'Option A']],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('options.0.description');
    }

    public function test_store_requires_title(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['title' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_store_requires_trigger_type(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['trigger_type' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('trigger_type');
    }

    public function test_store_rejects_invalid_trigger_type(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['trigger_type' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('trigger_type');
    }

    public function test_store_requires_description(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['description' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('description');
    }

    public function test_store_requires_cause(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['cause' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cause');
    }

    public function test_store_requires_impact(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['impact' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('impact');
    }

    public function test_store_requires_recommendation(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['recommendation' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recommendation');
    }

    public function test_store_forbidden_for_observer(): void
    {
        $p = Person::factory()->create();
        $u = User::factory()->create(['person_id' => $p->id]);
        $this->project->members()->create(['person_id' => $p->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($u)->postJson($this->indexUrl(), $this->storePayload())->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // show / update / destroy
    // -------------------------------------------------------------------------

    public function test_show_returns_report(): void
    {
        $report = $this->makeReport();

        $this->getJson($this->reportUrl($report))
            ->assertOk()
            ->assertJsonPath('data.id', $report->id);
    }

    public function test_update_edits_draft_report(): void
    {
        $report = $this->makeReport(['title' => 'Original']);

        $this->putJson($this->reportUrl($report), ['title' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated');
    }

    public function test_update_returns_409_on_submitted_report(): void
    {
        $report = $this->makeReport(['status' => ExceptionReportStatus::Submitted->value]);

        $this->putJson($this->reportUrl($report), ['title' => 'Updated'])->assertStatus(409);
    }

    public function test_destroy_deletes_draft_report(): void
    {
        $report = $this->makeReport(['status' => ExceptionReportStatus::Draft->value]);

        $this->deleteJson($this->reportUrl($report))->assertNoContent();
        $this->assertSoftDeleted('exception_reports', ['id' => $report->id]);
    }

    public function test_destroy_forbidden_on_submitted_report(): void
    {
        $report = $this->makeReport(['status' => ExceptionReportStatus::Submitted->value]);

        $this->deleteJson($this->reportUrl($report))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // submit
    // -------------------------------------------------------------------------

    public function test_submit_transitions_draft_to_submitted(): void
    {
        $report = $this->makeReport(['status' => ExceptionReportStatus::Draft->value]);

        $this->postJson($this->reportUrl($report) . '/submit')
            ->assertOk()
            ->assertJsonPath('data.status', ExceptionReportStatus::Submitted->value);

        $this->assertDatabaseHas('exception_reports', [
            'id'           => $report->id,
            'submitted_by' => $this->person->id,
        ]);
        $this->assertNotNull($report->fresh()->submitted_at);
    }

    public function test_submit_returns_409_if_not_draft(): void
    {
        $report = $this->makeReport(['status' => ExceptionReportStatus::Submitted->value]);

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
    // close
    // -------------------------------------------------------------------------

    public function test_close_transitions_submitted_to_closed(): void
    {
        [, $execUser] = $this->makeBoardUser(ProjectRole::Executive);
        $report = $this->makeReport(['status' => ExceptionReportStatus::Submitted->value]);

        $this->actingAs($execUser)
            ->postJson($this->reportUrl($report) . '/close', [
                'board_decision' => 'Approve exception plan to extend stage by 2 weeks.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ExceptionReportStatus::Closed->value)
            ->assertJsonPath('data.board_decision', 'Approve exception plan to extend stage by 2 weeks.');

        $this->assertNotNull($report->fresh()->decided_at);
    }

    public function test_close_requires_board_decision(): void
    {
        [, $execUser] = $this->makeBoardUser(ProjectRole::Executive);
        $report = $this->makeReport(['status' => ExceptionReportStatus::Submitted->value]);

        $this->actingAs($execUser)
            ->postJson($this->reportUrl($report) . '/close', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('board_decision');
    }

    public function test_close_returns_409_if_not_submitted(): void
    {
        [, $execUser] = $this->makeBoardUser(ProjectRole::Executive);
        $report = $this->makeReport(['status' => ExceptionReportStatus::Draft->value]);

        $this->actingAs($execUser)
            ->postJson($this->reportUrl($report) . '/close', ['board_decision' => 'Decision.'])
            ->assertStatus(409);
    }

    public function test_close_forbidden_for_pm(): void
    {
        $report = $this->makeReport(['status' => ExceptionReportStatus::Submitted->value]);

        $this->postJson($this->reportUrl($report) . '/close', ['board_decision' => 'Decision.'])
            ->assertForbidden();
    }

    public function test_close_allowed_for_senior_supplier(): void
    {
        [, $ssUser] = $this->makeBoardUser(ProjectRole::SeniorSupplier);
        $report = $this->makeReport(['status' => ExceptionReportStatus::Submitted->value]);

        $this->actingAs($ssUser)
            ->postJson($this->reportUrl($report) . '/close', ['board_decision' => 'Approved.'])
            ->assertOk()
            ->assertJsonPath('data.status', ExceptionReportStatus::Closed->value);
    }

    // -------------------------------------------------------------------------
    // raise-exception
    // -------------------------------------------------------------------------

    public function test_raise_exception_creates_draft_report_from_work_package(): void
    {
        $wp = WorkPackage::factory()->create([
            'project_id'      => $this->project->id,
            'team_manager_id' => $this->person->id,
            'created_by'      => $this->person->id,
            'title'           => 'Build API',
            'planned_start'   => '2026-05-01',
            'planned_end'     => '2026-05-31',
            'actual_end'      => '2026-06-10',
            'status'          => WorkPackageStatus::Completed->value,
        ]);

        $this->postJson("/api/projects/{$this->project->id}/work-packages/{$wp->id}/raise-exception")
            ->assertCreated()
            ->assertJsonPath('data.status', ExceptionReportStatus::Draft->value)
            ->assertJsonPath('data.trigger_type', ExceptionTriggerType::ToleranceTime->value)
            ->assertJsonPath('data.ref', 'EXR-001');

        $this->assertDatabaseHas('exception_reports', [
            'project_id'  => $this->project->id,
            'trigger_type' => ExceptionTriggerType::ToleranceTime->value,
            'created_by'  => $this->person->id,
        ]);
    }

    public function test_raise_exception_forbidden_for_team_manager(): void
    {
        $tmPerson = Person::factory()->create();
        $tmUser   = User::factory()->create(['person_id' => $tmPerson->id]);
        $this->project->members()->create(['person_id' => $tmPerson->id, 'role' => ProjectRole::TeamManager->value]);

        $wp = WorkPackage::factory()->create([
            'project_id'      => $this->project->id,
            'team_manager_id' => $tmPerson->id,
            'created_by'      => $this->person->id,
            'planned_start'   => '2026-05-01',
            'planned_end'     => '2026-05-31',
        ]);

        $this->actingAs($tmUser)
            ->postJson("/api/projects/{$this->project->id}/work-packages/{$wp->id}/raise-exception")
            ->assertForbidden();
    }
}
