<?php

namespace Tests\Feature\Projects;

use App\Enums\PlanStatus;
use App\Enums\ProjectRole;
use App\Enums\WorkPackageStatus;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Project;
use App\Models\Stage;
use App\Models\User;
use App\Models\WorkPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkPackageControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Person $person;
    private Project $project;
    private Person $teamManagerPerson;

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

        $this->teamManagerPerson = Person::factory()->create();
        $this->project->members()->create([
            'person_id' => $this->teamManagerPerson->id,
            'role'      => ProjectRole::TeamManager->value,
        ]);
    }

    private function indexUrl(): string
    {
        return "/api/projects/{$this->project->id}/work-packages";
    }

    private function workPackageUrl(WorkPackage $wp): string
    {
        return "/api/projects/{$this->project->id}/work-packages/{$wp->id}";
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title'           => 'Build Payment Gateway',
            'team_manager_id' => $this->teamManagerPerson->id,
            'planned_start'   => '2026-02-01',
            'planned_end'     => '2026-04-30',
        ], $overrides);
    }

    private function makeWorkPackage(array $attributes = []): WorkPackage
    {
        return WorkPackage::factory()->create(array_merge([
            'project_id'      => $this->project->id,
            'team_manager_id' => $this->teamManagerPerson->id,
            'created_by'      => $this->person->id,
        ], $attributes));
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_lists_work_packages(): void
    {
        $this->makeWorkPackage();
        $this->makeWorkPackage();

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

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_work_package(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.title', 'Build Payment Gateway')
            ->assertJsonPath('data.status', WorkPackageStatus::Draft->value);

        $this->assertDatabaseHas('work_packages', [
            'project_id'      => $this->project->id,
            'team_manager_id' => $this->teamManagerPerson->id,
            'created_by'      => $this->person->id,
        ]);
    }

    public function test_store_assigns_products(): void
    {
        $products = Product::factory()->count(2)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);

        $response = $this->postJson($this->indexUrl(), $this->validPayload([
            'product_ids' => $products->pluck('id')->toArray(),
        ]))->assertCreated();

        $wpId = $response->json('data.id');
        $this->assertCount(2, WorkPackage::find($wpId)->products);
    }

    public function test_store_with_plan_id(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->person->id]);
        $plan  = Plan::factory()->create([
            'project_id' => $this->project->id,
            'stage_id'   => $stage->id,
            'created_by' => $this->person->id,
        ]);

        $this->postJson($this->indexUrl(), $this->validPayload(['plan_id' => $plan->id]))
            ->assertCreated()
            ->assertJsonPath('data.plan_id', $plan->id);
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
            ->postJson($this->indexUrl(), $this->validPayload())
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // store – validation
    // -------------------------------------------------------------------------

    public function test_store_requires_title(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['title' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_store_requires_team_manager_id(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['team_manager_id' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('team_manager_id');
    }

    public function test_store_rejects_non_existent_team_manager_id(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['team_manager_id' => 99999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('team_manager_id');
    }

    public function test_store_requires_planned_start(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['planned_start' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('planned_start');
    }

    public function test_store_requires_planned_end(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['planned_end' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('planned_end');
    }

    public function test_store_rejects_planned_end_before_planned_start(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload([
            'planned_start' => '2026-05-01',
            'planned_end'   => '2026-04-01',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('planned_end');
    }

    public function test_store_rejects_plan_from_another_project(): void
    {
        $otherProject = Project::factory()->create(['created_by' => $this->person->id]);
        $otherStage   = Stage::factory()->create(['project_id' => $otherProject->id, 'created_by' => $this->person->id]);
        $foreignPlan  = Plan::factory()->create([
            'project_id' => $otherProject->id,
            'stage_id'   => $otherStage->id,
            'created_by' => $this->person->id,
        ]);

        $this->postJson($this->indexUrl(), $this->validPayload(['plan_id' => $foreignPlan->id]))
            ->assertUnprocessable();
    }

    public function test_store_rejects_team_manager_not_in_project(): void
    {
        $outsider = Person::factory()->create();

        $this->postJson($this->indexUrl(), $this->validPayload(['team_manager_id' => $outsider->id]))
            ->assertUnprocessable();
    }

    public function test_store_rejects_products_from_another_project(): void
    {
        $foreignProduct = Product::factory()->create([
            'project_id' => Project::factory()->create(['created_by' => $this->person->id])->id,
            'created_by' => $this->person->id,
        ]);

        $this->postJson($this->indexUrl(), $this->validPayload(['product_ids' => [$foreignProduct->id]]))
            ->assertUnprocessable();
    }

    public function test_store_rejects_non_existent_product_ids(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['product_ids' => [99999]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_ids.0');
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_work_package(): void
    {
        $wp = $this->makeWorkPackage();

        $this->getJson($this->workPackageUrl($wp))
            ->assertOk()
            ->assertJsonPath('data.id', $wp->id);
    }

    public function test_show_forbidden_for_non_member(): void
    {
        $wp       = $this->makeWorkPackage();
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->workPackageUrl($wp))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_edits_work_package(): void
    {
        $wp = $this->makeWorkPackage();

        $this->putJson($this->workPackageUrl($wp), ['title' => 'Revised Title'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Revised Title');
    }

    public function test_update_syncs_products(): void
    {
        $wp       = $this->makeWorkPackage();
        $products = Product::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);
        $wp->products()->sync([$products[0]->id, $products[1]->id]);

        $this->putJson($this->workPackageUrl($wp), ['product_ids' => [$products[2]->id]])
            ->assertOk();

        $this->assertCount(1, $wp->fresh()->products);
        $this->assertEquals($products[2]->id, $wp->fresh()->products->first()->id);
    }

    public function test_update_rejects_planned_end_before_planned_start(): void
    {
        $wp = $this->makeWorkPackage();

        $this->putJson($this->workPackageUrl($wp), [
            'planned_start' => '2026-09-01',
            'planned_end'   => '2026-08-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('planned_end');
    }

    public function test_update_forbidden_for_read_only_role(): void
    {
        $wp             = $this->makeWorkPackage();
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create([
            'person_id' => $observerPerson->id,
            'role'      => ProjectRole::Observer->value,
        ]);

        $this->actingAs($observer)
            ->putJson($this->workPackageUrl($wp), ['title' => 'Hijacked'])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_draft_work_package(): void
    {
        $wp = $this->makeWorkPackage(['status' => WorkPackageStatus::Draft->value]);

        $this->deleteJson($this->workPackageUrl($wp))->assertNoContent();

        $this->assertSoftDeleted('work_packages', ['id' => $wp->id]);
    }

    public function test_destroy_forbidden_on_authorized_work_package(): void
    {
        $wp = $this->makeWorkPackage([
            'status'        => WorkPackageStatus::Authorized->value,
            'authorized_by' => $this->person->id,
            'authorized_at' => now(),
        ]);

        $this->deleteJson($this->workPackageUrl($wp))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // authorize
    // -------------------------------------------------------------------------

    public function test_authorize_transitions_draft_to_authorized(): void
    {
        $wp = $this->makeWorkPackage(['status' => WorkPackageStatus::Draft->value]);

        $this->postJson("/api/projects/{$this->project->id}/work-packages/{$wp->id}/authorize")
            ->assertOk()
            ->assertJsonPath('data.status', WorkPackageStatus::Authorized->value);

        $this->assertDatabaseHas('work_packages', [
            'id'            => $wp->id,
            'status'        => WorkPackageStatus::Authorized->value,
            'authorized_by' => $this->person->id,
        ]);
    }

    public function test_authorize_returns_409_if_not_draft(): void
    {
        $wp = $this->makeWorkPackage([
            'status'        => WorkPackageStatus::Authorized->value,
            'authorized_by' => $this->person->id,
            'authorized_at' => now(),
        ]);

        $this->postJson("/api/projects/{$this->project->id}/work-packages/{$wp->id}/authorize")
            ->assertStatus(409);
    }

    public function test_authorize_forbidden_for_non_pm(): void
    {
        $wp             = $this->makeWorkPackage(['status' => WorkPackageStatus::Draft->value]);
        $supportPerson  = Person::factory()->create();
        $supportUser    = User::factory()->create(['person_id' => $supportPerson->id]);
        $this->project->members()->create([
            'person_id' => $supportPerson->id,
            'role'      => ProjectRole::ProjectSupport->value,
        ]);

        $this->actingAs($supportUser)
            ->postJson("/api/projects/{$this->project->id}/work-packages/{$wp->id}/authorize")
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // accept
    // -------------------------------------------------------------------------

    public function test_accept_transitions_authorized_to_in_progress(): void
    {
        $wp          = $this->makeWorkPackage(['status' => WorkPackageStatus::Authorized->value]);
        $tmUser      = User::factory()->create(['person_id' => $this->teamManagerPerson->id]);

        $this->actingAs($tmUser)
            ->postJson("/api/projects/{$this->project->id}/work-packages/{$wp->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', WorkPackageStatus::InProgress->value);

        $this->assertDatabaseHas('work_packages', [
            'id'     => $wp->id,
            'status' => WorkPackageStatus::InProgress->value,
        ]);
        $this->assertNotNull($wp->fresh()->actual_start);
    }

    public function test_accept_returns_409_if_not_authorized(): void
    {
        $wp     = $this->makeWorkPackage(['status' => WorkPackageStatus::Draft->value]);
        $tmUser = User::factory()->create(['person_id' => $this->teamManagerPerson->id]);

        $this->actingAs($tmUser)
            ->postJson("/api/projects/{$this->project->id}/work-packages/{$wp->id}/accept")
            ->assertStatus(409);
    }

    public function test_accept_forbidden_for_non_team_manager(): void
    {
        $wp = $this->makeWorkPackage(['status' => WorkPackageStatus::Authorized->value]);

        // $this->user is the PM, not the assigned team manager
        $this->postJson("/api/projects/{$this->project->id}/work-packages/{$wp->id}/accept")
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // complete
    // -------------------------------------------------------------------------

    public function test_complete_transitions_in_progress_to_completed(): void
    {
        $wp     = $this->makeWorkPackage(['status' => WorkPackageStatus::InProgress->value]);
        $tmUser = User::factory()->create(['person_id' => $this->teamManagerPerson->id]);

        $this->actingAs($tmUser)
            ->postJson("/api/projects/{$this->project->id}/work-packages/{$wp->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', WorkPackageStatus::Completed->value);

        $this->assertNotNull($wp->fresh()->actual_end);
    }

    public function test_complete_returns_409_if_not_in_progress(): void
    {
        $wp     = $this->makeWorkPackage(['status' => WorkPackageStatus::Authorized->value]);
        $tmUser = User::factory()->create(['person_id' => $this->teamManagerPerson->id]);

        $this->actingAs($tmUser)
            ->postJson("/api/projects/{$this->project->id}/work-packages/{$wp->id}/complete")
            ->assertStatus(409);
    }

    public function test_complete_forbidden_for_non_team_manager(): void
    {
        $wp = $this->makeWorkPackage(['status' => WorkPackageStatus::InProgress->value]);

        // $this->user is the PM
        $this->postJson("/api/projects/{$this->project->id}/work-packages/{$wp->id}/complete")
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // cancel
    // -------------------------------------------------------------------------

    public function test_cancel_transitions_authorized_to_cancelled(): void
    {
        $wp = $this->makeWorkPackage(['status' => WorkPackageStatus::Authorized->value]);

        $this->postJson("/api/projects/{$this->project->id}/work-packages/{$wp->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', WorkPackageStatus::Cancelled->value);
    }

    public function test_cancel_transitions_in_progress_to_cancelled(): void
    {
        $wp = $this->makeWorkPackage(['status' => WorkPackageStatus::InProgress->value]);

        $this->postJson("/api/projects/{$this->project->id}/work-packages/{$wp->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', WorkPackageStatus::Cancelled->value);
    }

    public function test_cancel_returns_409_if_already_completed(): void
    {
        $wp = $this->makeWorkPackage(['status' => WorkPackageStatus::Completed->value]);

        $this->postJson("/api/projects/{$this->project->id}/work-packages/{$wp->id}/cancel")
            ->assertStatus(409);
    }

    public function test_cancel_returns_409_if_draft(): void
    {
        $wp = $this->makeWorkPackage(['status' => WorkPackageStatus::Draft->value]);

        $this->postJson("/api/projects/{$this->project->id}/work-packages/{$wp->id}/cancel")
            ->assertStatus(409);
    }

    public function test_cancel_forbidden_for_non_pm(): void
    {
        $wp             = $this->makeWorkPackage(['status' => WorkPackageStatus::Authorized->value]);
        $supportPerson  = Person::factory()->create();
        $supportUser    = User::factory()->create(['person_id' => $supportPerson->id]);
        $this->project->members()->create([
            'person_id' => $supportPerson->id,
            'role'      => ProjectRole::ProjectSupport->value,
        ]);

        $this->actingAs($supportUser)
            ->postJson("/api/projects/{$this->project->id}/work-packages/{$wp->id}/cancel")
            ->assertForbidden();
    }
}
