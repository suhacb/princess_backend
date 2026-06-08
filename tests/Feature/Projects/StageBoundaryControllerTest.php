<?php

namespace Tests\Feature\Projects;

use App\Enums\BoundaryStatus;
use App\Enums\BoundaryType;
use App\Models\Person;
use App\Models\Project;
use App\Models\Stage;
use App\Models\StageBoundary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StageBoundaryControllerTest extends TestCase
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

        foreach (['project_manager', 'project_board', 'quality_assurance', 'team_manager', 'observer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->person  = Person::factory()->create();
        $this->user    = User::factory()->create(['person_id' => $this->person->id]);
        $this->user->assignRole('project_manager');
        $this->actingAs($this->user);

        $this->project = Project::factory()->create(['created_by' => $this->person->id]);
        $this->stage   = Stage::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);
    }

    private function boundaryUrl(?StageBoundary $boundary = null): string
    {
        $base = "/api/projects/{$this->project->id}/stages/{$this->stage->id}/boundaries";
        return $boundary ? "{$base}/{$boundary->id}" : $base;
    }

    public function test_index_lists_boundaries_for_stage(): void
    {
        StageBoundary::factory()->count(2)->create([
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
        ]);

        $this->getJson($this->boundaryUrl())->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_store_creates_boundary(): void
    {
        $this->postJson($this->boundaryUrl(), ['type' => BoundaryType::EndStageReport->value])
            ->assertCreated()
            ->assertJsonPath('data.type', BoundaryType::EndStageReport->value)
            ->assertJsonPath('data.status', BoundaryStatus::Draft->value);
    }

    public function test_store_returns_422_when_type_missing(): void
    {
        $this->postJson($this->boundaryUrl(), [])->assertUnprocessable()->assertJsonValidationErrors(['type']);
    }

    public function test_show_returns_boundary(): void
    {
        $boundary = StageBoundary::factory()->create([
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
        ]);

        $this->getJson($this->boundaryUrl($boundary))
            ->assertOk()
            ->assertJsonPath('data.id', $boundary->id);
    }

    public function test_update_modifies_draft_boundary(): void
    {
        $boundary = StageBoundary::factory()->create([
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
        ]);

        $this->putJson($this->boundaryUrl($boundary), ['title' => 'Updated title'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title');
    }

    public function test_update_rejects_non_draft_boundary(): void
    {
        $boundary = StageBoundary::factory()->create([
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
            'status'     => BoundaryStatus::Submitted,
        ]);

        $this->putJson($this->boundaryUrl($boundary), ['title' => 'Should fail'])
            ->assertStatus(409);
    }

    public function test_destroy_deletes_draft_boundary(): void
    {
        $boundary = StageBoundary::factory()->create([
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
        ]);

        $this->deleteJson($this->boundaryUrl($boundary))->assertNoContent();
        $this->assertDatabaseMissing('stage_boundaries', ['id' => $boundary->id]);
    }

    public function test_destroy_rejects_non_draft_boundary(): void
    {
        $boundary = StageBoundary::factory()->create([
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
            'status'     => BoundaryStatus::Submitted,
        ]);

        $this->deleteJson($this->boundaryUrl($boundary))->assertStatus(409);
    }

    public function test_submit_transitions_draft_to_submitted(): void
    {
        $boundary = StageBoundary::factory()->create([
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
        ]);

        $this->patchJson("{$this->boundaryUrl($boundary)}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', BoundaryStatus::Submitted->value);

        $this->assertNotNull($boundary->fresh()->submitted_at);
    }

    public function test_submit_rejects_non_draft_boundary(): void
    {
        $boundary = StageBoundary::factory()->create([
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
            'status'     => BoundaryStatus::Submitted,
        ]);

        $this->patchJson("{$this->boundaryUrl($boundary)}/submit")->assertStatus(409);
    }

    public function test_approve_transitions_submitted_to_approved(): void
    {
        $boundary = StageBoundary::factory()->create([
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
            'status'     => BoundaryStatus::Submitted,
        ]);

        $this->patchJson("{$this->boundaryUrl($boundary)}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', BoundaryStatus::Approved->value);

        $this->assertNotNull($boundary->fresh()->approved_at);
    }

    public function test_approve_rejects_non_submitted_boundary(): void
    {
        $boundary = StageBoundary::factory()->create([
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
        ]);

        $this->patchJson("{$this->boundaryUrl($boundary)}/approve")->assertStatus(409);
    }

    public function test_reject_transitions_submitted_to_rejected(): void
    {
        $boundary = StageBoundary::factory()->create([
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
            'status'     => BoundaryStatus::Submitted,
        ]);

        $this->patchJson("{$this->boundaryUrl($boundary)}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', BoundaryStatus::Rejected->value);
    }
}
