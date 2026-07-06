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

        $this->person  = Person::factory()->create();
        $this->user    = User::factory()->create(['person_id' => $this->person->id]);
        $this->actingAs($this->user);

        $this->project = Project::factory()->create(['created_by' => $this->person->id]);
        $this->project->members()->create([
            'person_id' => $this->person->id,
            'role'      => 'project_manager',
        ]);

        $this->stage = Stage::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);
    }

    private function boundaryUrl(?StageBoundary $boundary = null): string
    {
        $base = "/api/projects/{$this->project->id}/stages/{$this->stage->id}/boundaries";
        return $boundary ? "{$base}/{$boundary->id}" : $base;
    }

    private function makeBoardUser(): User
    {
        $approver  = Person::factory()->create();
        $boardUser = User::factory()->create(['person_id' => $approver->id]);
        $this->project->members()->create([
            'person_id' => $approver->id,
            'role'      => 'executive',
        ]);
        return $boardUser;
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

    public function test_store_rejects_invalid_type(): void
    {
        $this->postJson($this->boundaryUrl(), ['type' => 'not_a_type'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_store_accepts_title_and_notes_and_next_stage_id(): void
    {
        $nextStage = Stage::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);

        $this->postJson($this->boundaryUrl(), [
            'type'          => BoundaryType::EndStageReport->value,
            'title'         => 'End of Stage 1',
            'notes'         => 'All deliverables complete.',
            'next_stage_id' => $nextStage->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'End of Stage 1')
            ->assertJsonPath('data.notes', 'All deliverables complete.')
            ->assertJsonPath('data.next_stage_id', $nextStage->id);
    }

    public function test_store_rejects_title_exceeding_max_length(): void
    {
        $this->postJson($this->boundaryUrl(), [
            'type'  => BoundaryType::EndStageReport->value,
            'title' => str_repeat('a', 256),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_store_rejects_non_integer_next_stage_id(): void
    {
        $this->postJson($this->boundaryUrl(), [
            'type'          => BoundaryType::EndStageReport->value,
            'next_stage_id' => 'not-an-integer',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('next_stage_id');
    }

    public function test_store_requires_exception_summary_when_type_is_exception_report(): void
    {
        $this->postJson($this->boundaryUrl(), ['type' => BoundaryType::ExceptionReport->value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('exception_summary');
    }

    public function test_store_accepts_exception_report_with_exception_summary(): void
    {
        $this->postJson($this->boundaryUrl(), [
            'type'              => BoundaryType::ExceptionReport->value,
            'exception_summary' => 'Budget tolerance exceeded.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.exception_summary', 'Budget tolerance exceeded.');
    }

    public function test_store_does_not_require_exception_summary_for_other_types(): void
    {
        $this->postJson($this->boundaryUrl(), ['type' => BoundaryType::EndStageReport->value])
            ->assertCreated();
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

    public function test_update_rejects_title_exceeding_max_length(): void
    {
        $boundary = StageBoundary::factory()->create([
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
        ]);

        $this->putJson($this->boundaryUrl($boundary), ['title' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_update_modifies_notes(): void
    {
        $boundary = StageBoundary::factory()->create([
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
        ]);

        $this->putJson($this->boundaryUrl($boundary), ['notes' => 'Updated notes.'])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Updated notes.');
    }

    public function test_update_modifies_next_stage_id(): void
    {
        $boundary = StageBoundary::factory()->create([
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
        ]);
        $nextStage = Stage::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);

        $this->putJson($this->boundaryUrl($boundary), ['next_stage_id' => $nextStage->id])
            ->assertOk()
            ->assertJsonPath('data.next_stage_id', $nextStage->id);
    }

    public function test_update_rejects_non_integer_next_stage_id(): void
    {
        $boundary = StageBoundary::factory()->create([
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
        ]);

        $this->putJson($this->boundaryUrl($boundary), ['next_stage_id' => 'not-an-integer'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('next_stage_id');
    }

    public function test_update_modifies_exception_summary(): void
    {
        $boundary = StageBoundary::factory()->create([
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
            'type'       => BoundaryType::ExceptionReport,
        ]);

        $this->putJson($this->boundaryUrl($boundary), ['exception_summary' => 'Revised exception summary.'])
            ->assertOk()
            ->assertJsonPath('data.exception_summary', 'Revised exception summary.');
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
        $boardUser = $this->makeBoardUser();

        $boundary = StageBoundary::factory()->create([
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
            'status'     => BoundaryStatus::Submitted,
        ]);

        $this->actingAs($boardUser)
            ->patchJson("{$this->boundaryUrl($boundary)}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', BoundaryStatus::Approved->value);

        $this->assertNotNull($boundary->fresh()->approved_at);
    }

    public function test_approve_rejects_non_submitted_boundary(): void
    {
        $boardUser = $this->makeBoardUser();

        $boundary = StageBoundary::factory()->create([
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
        ]);

        $this->actingAs($boardUser)
            ->patchJson("{$this->boundaryUrl($boundary)}/approve")
            ->assertStatus(409);
    }

    public function test_approve_forbidden_for_project_manager(): void
    {
        $boundary = StageBoundary::factory()->create([
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
            'status'     => BoundaryStatus::Submitted,
        ]);

        $this->patchJson("{$this->boundaryUrl($boundary)}/approve")->assertForbidden();
    }

    public function test_reject_transitions_submitted_to_rejected(): void
    {
        $boardUser = $this->makeBoardUser();

        $boundary = StageBoundary::factory()->create([
            'stage_id'   => $this->stage->id,
            'created_by' => $this->person->id,
            'status'     => BoundaryStatus::Submitted,
        ]);

        $this->actingAs($boardUser)
            ->patchJson("{$this->boundaryUrl($boundary)}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', BoundaryStatus::Rejected->value);
    }
}
