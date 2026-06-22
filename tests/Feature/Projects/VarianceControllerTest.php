<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\WorkPackageStatus;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Stage;
use App\Models\User;
use App\Models\WorkPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VarianceControllerTest extends TestCase
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

    private function projectVarianceUrl(): string
    {
        return "/api/projects/{$this->project->id}/variance";
    }

    private function stageVarianceUrl(Stage $stage): string
    {
        return "/api/projects/{$this->project->id}/stages/{$stage->id}/variance";
    }

    private function makePlan(array $attributes = []): Plan
    {
        return Plan::factory()->create(array_merge([
            'project_id'   => $this->project->id,
            'created_by'   => $this->person->id,
            'planned_start' => '2026-05-01',
            'planned_end'  => '2026-06-30',
        ], $attributes));
    }

    private function makeWp(array $attributes = []): WorkPackage
    {
        return WorkPackage::factory()->create(array_merge([
            'project_id'      => $this->project->id,
            'team_manager_id' => $this->person->id,
            'created_by'      => $this->person->id,
            'planned_start'   => '2026-05-01',
            'planned_end'     => '2026-05-31',
        ], $attributes));
    }

    // -------------------------------------------------------------------------
    // project-level variance
    // -------------------------------------------------------------------------

    public function test_index_returns_expected_structure(): void
    {
        $this->makePlan();

        $this->getJson($this->projectVarianceUrl())
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'stage',
                    'plans'         => [['id', 'name', 'planned_end', 'actual_end', 'time_variance_days', 'tolerance_time', 'tolerance_breached']],
                    'work_packages',
                ],
            ]);
    }

    public function test_index_stage_is_null_for_project_level(): void
    {
        $this->getJson($this->projectVarianceUrl())
            ->assertOk()
            ->assertJsonPath('data.stage', null);
    }

    public function test_index_includes_all_project_plans_and_wps(): void
    {
        $this->makePlan();
        $this->makePlan();
        $this->makeWp();

        $data = $this->getJson($this->projectVarianceUrl())->assertOk()->json('data');

        $this->assertCount(2, $data['plans']);
        $this->assertCount(1, $data['work_packages']);
    }

    // -------------------------------------------------------------------------
    // variance computation
    // -------------------------------------------------------------------------

    public function test_variance_is_null_when_not_completed(): void
    {
        $this->makeWp(['actual_end' => null]);

        $data = $this->getJson($this->projectVarianceUrl())->assertOk()->json('data');

        $this->assertNull($data['work_packages'][0]['time_variance_days']);
        $this->assertFalse($data['work_packages'][0]['tolerance_breached']);
    }

    public function test_variance_is_positive_when_late(): void
    {
        $this->makeWp([
            'planned_end' => '2026-05-31',
            'actual_end'  => '2026-06-05',
            'status'      => WorkPackageStatus::Completed->value,
        ]);

        $data = $this->getJson($this->projectVarianceUrl())->assertOk()->json('data');

        $this->assertEquals(5, $data['work_packages'][0]['time_variance_days']);
    }

    public function test_variance_is_negative_when_early(): void
    {
        $this->makeWp([
            'planned_end' => '2026-05-31',
            'actual_end'  => '2026-05-28',
            'status'      => WorkPackageStatus::Completed->value,
        ]);

        $data = $this->getJson($this->projectVarianceUrl())->assertOk()->json('data');

        $this->assertEquals(-3, $data['work_packages'][0]['time_variance_days']);
    }

    // -------------------------------------------------------------------------
    // tolerance breach
    // -------------------------------------------------------------------------

    public function test_tolerance_breached_when_variance_exceeds_days_suffix(): void
    {
        $this->makeWp([
            'planned_end'    => '2026-05-31',
            'actual_end'     => '2026-06-05',
            'status'         => WorkPackageStatus::Completed->value,
            'tolerance_time' => '3d',
        ]);

        $data = $this->getJson($this->projectVarianceUrl())->assertOk()->json('data');

        $this->assertTrue($data['work_packages'][0]['tolerance_breached']);
    }

    public function test_tolerance_not_breached_within_tolerance(): void
    {
        $this->makeWp([
            'planned_end'    => '2026-05-31',
            'actual_end'     => '2026-06-03',
            'status'         => WorkPackageStatus::Completed->value,
            'tolerance_time' => '5d',
        ]);

        $data = $this->getJson($this->projectVarianceUrl())->assertOk()->json('data');

        $this->assertFalse($data['work_packages'][0]['tolerance_breached']);
    }

    public function test_tolerance_parsed_with_weeks_suffix(): void
    {
        $this->makeWp([
            'planned_end'    => '2026-05-31',
            'actual_end'     => '2026-06-10',
            'status'         => WorkPackageStatus::Completed->value,
            'tolerance_time' => '2w',
        ]);

        $data = $this->getJson($this->projectVarianceUrl())->assertOk()->json('data');

        // 10 days variance, 2w = 14 days tolerance → not breached
        $this->assertFalse($data['work_packages'][0]['tolerance_breached']);
    }

    public function test_tolerance_not_breached_when_no_tolerance_set(): void
    {
        $this->makeWp([
            'planned_end'    => '2026-05-31',
            'actual_end'     => '2026-06-30',
            'status'         => WorkPackageStatus::Completed->value,
            'tolerance_time' => null,
        ]);

        $data = $this->getJson($this->projectVarianceUrl())->assertOk()->json('data');

        $this->assertFalse($data['work_packages'][0]['tolerance_breached']);
    }

    // -------------------------------------------------------------------------
    // stage-level variance
    // -------------------------------------------------------------------------

    public function test_show_returns_stage_and_its_plans(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->person->id]);
        $this->makePlan(['stage_id' => $stage->id]);
        $this->makePlan(); // plan not linked to this stage

        $data = $this->getJson($this->stageVarianceUrl($stage))->assertOk()->json('data');

        $this->assertEquals($stage->id, $data['stage']['id']);
        $this->assertCount(1, $data['plans']);
    }

    public function test_show_includes_wps_linked_to_stage_plans(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->person->id]);
        $plan  = $this->makePlan(['stage_id' => $stage->id]);
        $this->makeWp(['plan_id' => $plan->id]);
        $this->makeWp(); // WP with no plan

        $data = $this->getJson($this->stageVarianceUrl($stage))->assertOk()->json('data');

        $this->assertCount(1, $data['work_packages']);
    }

    public function test_show_returns_empty_for_stage_with_no_plans(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->person->id]);

        $data = $this->getJson($this->stageVarianceUrl($stage))->assertOk()->json('data');

        $this->assertCount(0, $data['plans']);
        $this->assertCount(0, $data['work_packages']);
    }

    public function test_show_returns_404_for_stage_from_another_project(): void
    {
        $otherProject = Project::factory()->create(['created_by' => $this->person->id]);
        $foreignStage = Stage::factory()->create(['project_id' => $otherProject->id, 'created_by' => $this->person->id]);

        $this->getJson($this->stageVarianceUrl($foreignStage))->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // authorization
    // -------------------------------------------------------------------------

    public function test_forbidden_for_non_member(): void
    {
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)->getJson($this->projectVarianceUrl())->assertForbidden();
    }

    public function test_accessible_for_observer(): void
    {
        $p = Person::factory()->create();
        $u = User::factory()->create(['person_id' => $p->id]);
        $this->project->members()->create(['person_id' => $p->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($u)->getJson($this->projectVarianceUrl())->assertOk();
    }
}
