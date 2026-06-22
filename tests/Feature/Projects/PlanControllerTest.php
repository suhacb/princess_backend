<?php

namespace Tests\Feature\Projects;

use App\Enums\PlanStatus;
use App\Enums\PlanType;
use App\Enums\ProjectRole;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Person $person;
    private Project $project;
    private Stage $stage;

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

        $this->stage = Stage::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);
    }

    private function indexUrl(): string
    {
        return "/api/projects/{$this->project->id}/plans";
    }

    private function planUrl(Plan $plan): string
    {
        return "/api/projects/{$this->project->id}/plans/{$plan->id}";
    }

    private function validStagePlanPayload(array $overrides = []): array
    {
        return array_merge([
            'type'          => PlanType::Stage->value,
            'name'          => 'Initiation Stage Plan',
            'planned_start' => '2026-01-01',
            'planned_end'   => '2026-06-30',
            'stage_id'      => $this->stage->id,
        ], $overrides);
    }

    private function validTeamPlanPayload(array $overrides = []): array
    {
        return array_merge([
            'type'          => PlanType::Team->value,
            'name'          => 'Team Plan Alpha',
            'planned_start' => '2026-02-01',
            'planned_end'   => '2026-05-31',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_lists_plans(): void
    {
        Plan::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
        ]);

        $this->getJson($this->indexUrl())
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_index_filters_by_type(): void
    {
        Plan::factory()->count(2)->create([
            'project_id' => $this->project->id,
            'stage_id'   => $this->stage->id,
            'type'       => PlanType::Stage->value,
            'created_by' => $this->person->id,
        ]);
        Plan::factory()->team()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);

        $this->getJson($this->indexUrl() . '?type=team')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', PlanType::Team->value);
    }

    public function test_index_forbidden_for_non_member(): void
    {
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->indexUrl())
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_stage_plan(): void
    {
        $this->postJson($this->indexUrl(), $this->validStagePlanPayload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Initiation Stage Plan')
            ->assertJsonPath('data.type', PlanType::Stage->value)
            ->assertJsonPath('data.status', PlanStatus::Draft->value);

        $this->assertDatabaseHas('plans', [
            'project_id' => $this->project->id,
            'type'       => PlanType::Stage->value,
            'created_by' => $this->person->id,
        ]);
    }

    public function test_store_creates_team_plan(): void
    {
        $this->postJson($this->indexUrl(), $this->validTeamPlanPayload())
            ->assertCreated()
            ->assertJsonPath('data.type', PlanType::Team->value)
            ->assertJsonPath('data.status', PlanStatus::Draft->value);
    }

    public function test_store_creates_exception_plan(): void
    {
        $replaced = Plan::factory()->create([
            'project_id' => $this->project->id,
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
        ]);

        $this->postJson($this->indexUrl(), [
            'type'             => PlanType::Exception->value,
            'name'             => 'Exception Plan',
            'planned_start'    => '2026-03-01',
            'planned_end'      => '2026-07-31',
            'stage_id'         => $this->stage->id,
            'replaces_plan_id' => $replaced->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', PlanType::Exception->value)
            ->assertJsonPath('data.replaces_plan_id', $replaced->id);
    }

    public function test_store_sets_created_by_from_auth_user(): void
    {
        $this->postJson($this->indexUrl(), $this->validTeamPlanPayload())
            ->assertCreated();

        $this->assertDatabaseHas('plans', ['created_by' => $this->person->id]);
    }

    public function test_store_forbidden_for_read_only_role(): void
    {
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create([
            'person_id' => $observerPerson->id,
            'role'      => ProjectRole::Observer->value,
        ]);

        $this->actingAs($observer)
            ->postJson($this->indexUrl(), $this->validStagePlanPayload())
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // store – validation
    // -------------------------------------------------------------------------

    public function test_store_requires_type(): void
    {
        $this->postJson($this->indexUrl(), $this->validStagePlanPayload(['type' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_store_rejects_invalid_type(): void
    {
        $this->postJson($this->indexUrl(), $this->validStagePlanPayload(['type' => 'bogus']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_store_requires_name(): void
    {
        $this->postJson($this->indexUrl(), $this->validStagePlanPayload(['name' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_store_requires_planned_start(): void
    {
        $this->postJson($this->indexUrl(), $this->validStagePlanPayload(['planned_start' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('planned_start');
    }

    public function test_store_requires_planned_end(): void
    {
        $this->postJson($this->indexUrl(), $this->validStagePlanPayload(['planned_end' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('planned_end');
    }

    public function test_store_rejects_planned_end_before_planned_start(): void
    {
        $this->postJson($this->indexUrl(), $this->validStagePlanPayload([
            'planned_start' => '2026-06-01',
            'planned_end'   => '2026-05-01',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('planned_end');
    }

    public function test_store_stage_plan_requires_stage_id(): void
    {
        $this->postJson($this->indexUrl(), $this->validStagePlanPayload(['stage_id' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stage_id');
    }

    public function test_store_stage_plan_rejects_non_existent_stage_id(): void
    {
        $this->postJson($this->indexUrl(), $this->validStagePlanPayload(['stage_id' => 99999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stage_id');
    }

    public function test_store_exception_plan_requires_stage_id(): void
    {
        $replaced = Plan::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);

        $this->postJson($this->indexUrl(), [
            'type'             => PlanType::Exception->value,
            'name'             => 'Exception Plan',
            'planned_start'    => '2026-03-01',
            'planned_end'      => '2026-07-31',
            'replaces_plan_id' => $replaced->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stage_id');
    }

    public function test_store_exception_plan_requires_replaces_plan_id(): void
    {
        $this->postJson($this->indexUrl(), [
            'type'          => PlanType::Exception->value,
            'name'          => 'Exception Plan',
            'planned_start' => '2026-03-01',
            'planned_end'   => '2026-07-31',
            'stage_id'      => $this->stage->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('replaces_plan_id');
    }

    public function test_store_rejects_replaces_plan_id_from_another_project(): void
    {
        $otherProject = Project::factory()->create(['created_by' => $this->person->id]);
        $otherStage   = Stage::factory()->create(['project_id' => $otherProject->id, 'created_by' => $this->person->id]);
        $foreignPlan  = Plan::factory()->create([
            'project_id' => $otherProject->id,
            'stage_id'   => $otherStage->id,
            'created_by' => $this->person->id,
        ]);

        $this->postJson($this->indexUrl(), [
            'type'             => PlanType::Exception->value,
            'name'             => 'Exception Plan',
            'planned_start'    => '2026-03-01',
            'planned_end'      => '2026-07-31',
            'stage_id'         => $this->stage->id,
            'replaces_plan_id' => $foreignPlan->id,
        ])
            ->assertUnprocessable();
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_plan(): void
    {
        $plan = Plan::factory()->create([
            'project_id' => $this->project->id,
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
        ]);

        $this->getJson($this->planUrl($plan))
            ->assertOk()
            ->assertJsonPath('data.id', $plan->id);
    }

    public function test_show_forbidden_for_non_member(): void
    {
        $plan     = Plan::factory()->create([
            'project_id' => $this->project->id,
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
        ]);
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->planUrl($plan))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_edits_plan(): void
    {
        $plan = Plan::factory()->create([
            'project_id' => $this->project->id,
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
        ]);

        $this->putJson($this->planUrl($plan), ['name' => 'Revised Plan Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Revised Plan Name');
    }

    public function test_update_rejects_planned_end_before_planned_start(): void
    {
        $plan = Plan::factory()->create([
            'project_id' => $this->project->id,
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
        ]);

        $this->putJson($this->planUrl($plan), [
            'planned_start' => '2026-08-01',
            'planned_end'   => '2026-07-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('planned_end');
    }

    public function test_update_forbidden_for_read_only_role(): void
    {
        $plan           = Plan::factory()->create([
            'project_id' => $this->project->id,
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
        ]);
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create([
            'person_id' => $observerPerson->id,
            'role'      => ProjectRole::Observer->value,
        ]);

        $this->actingAs($observer)
            ->putJson($this->planUrl($plan), ['name' => 'Hijacked'])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_draft_plan(): void
    {
        $plan = Plan::factory()->create([
            'project_id' => $this->project->id,
            'stage_id'   => $this->stage->id,
            'status'     => PlanStatus::Draft->value,
            'created_by' => $this->person->id,
        ]);

        $this->deleteJson($this->planUrl($plan))->assertNoContent();

        $this->assertSoftDeleted('plans', ['id' => $plan->id]);
    }

    public function test_destroy_forbidden_on_approved_plan(): void
    {
        $plan = Plan::factory()->create([
            'project_id'  => $this->project->id,
            'stage_id'    => $this->stage->id,
            'status'      => PlanStatus::Approved->value,
            'approved_by' => $this->person->id,
            'approved_at' => now(),
            'created_by'  => $this->person->id,
        ]);

        $this->deleteJson($this->planUrl($plan))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // approve
    // -------------------------------------------------------------------------

    public function test_approve_transitions_plan_to_approved(): void
    {
        $plan = Plan::factory()->create([
            'project_id' => $this->project->id,
            'stage_id'   => $this->stage->id,
            'status'     => PlanStatus::Draft->value,
            'created_by' => $this->person->id,
        ]);

        $execPerson = Person::factory()->create();
        $exec       = User::factory()->create(['person_id' => $execPerson->id]);
        $this->project->members()->create([
            'person_id' => $execPerson->id,
            'role'      => ProjectRole::Executive->value,
        ]);

        $this->actingAs($exec)
            ->postJson("/api/projects/{$this->project->id}/plans/{$plan->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', PlanStatus::Approved->value);

        $this->assertDatabaseHas('plans', [
            'id'          => $plan->id,
            'status'      => PlanStatus::Approved->value,
            'approved_by' => $execPerson->id,
        ]);
    }

    public function test_approve_allowed_for_all_board_roles(): void
    {
        $boardRoles = [
            ProjectRole::Executive,
            ProjectRole::SeniorUser,
            ProjectRole::SeniorSupplier,
        ];

        foreach ($boardRoles as $role) {
            $boardPerson = Person::factory()->create();
            $boardUser   = User::factory()->create(['person_id' => $boardPerson->id]);
            $this->project->members()->create([
                'person_id' => $boardPerson->id,
                'role'      => $role->value,
            ]);

            $plan = Plan::factory()->create([
                'project_id' => $this->project->id,
                'stage_id'   => $this->stage->id,
                'status'     => PlanStatus::Draft->value,
                'created_by' => $this->person->id,
            ]);

            $this->actingAs($boardUser)
                ->postJson("/api/projects/{$this->project->id}/plans/{$plan->id}/approve")
                ->assertOk("Board role {$role->value} should be able to approve");
        }
    }

    public function test_approve_forbidden_for_project_manager(): void
    {
        $plan = Plan::factory()->create([
            'project_id' => $this->project->id,
            'stage_id'   => $this->stage->id,
            'status'     => PlanStatus::Draft->value,
            'created_by' => $this->person->id,
        ]);

        // $this->user is already a ProjectManager (set up in setUp)
        $this->postJson("/api/projects/{$this->project->id}/plans/{$plan->id}/approve")
            ->assertForbidden();
    }

    public function test_approve_forbidden_for_non_member(): void
    {
        $plan     = Plan::factory()->create([
            'project_id' => $this->project->id,
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
        ]);
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->postJson("/api/projects/{$this->project->id}/plans/{$plan->id}/approve")
            ->assertForbidden();
    }
}
