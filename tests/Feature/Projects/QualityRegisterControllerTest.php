<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\QualityMethod;
use App\Enums\QualityResult;
use App\Models\Person;
use App\Models\Project;
use App\Models\QualityRegisterEntry;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualityRegisterControllerTest extends TestCase
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
        return "/api/projects/{$this->project->id}/quality-register";
    }

    private function entryUrl(QualityRegisterEntry $entry): string
    {
        return "/api/projects/{$this->project->id}/quality-register/{$entry->id}";
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'product_name'   => 'Project Initiation Document',
            'quality_method' => QualityMethod::Review->value,
            'planned_date'   => '2026-07-01',
        ], $overrides);
    }

    public function test_index_lists_entries(): void
    {
        QualityRegisterEntry::factory()->count(2)->create([
            'project_id' => $this->project->id,
        ]);

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

    public function test_store_creates_entry(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.product_name', 'Project Initiation Document')
            ->assertJsonPath('data.quality_method', QualityMethod::Review->value);

        $this->assertDatabaseHas('quality_register_entries', [
            'project_id'   => $this->project->id,
            'product_name' => 'Project Initiation Document',
        ]);
    }

    public function test_store_forbidden_for_executive(): void
    {
        $execPerson = Person::factory()->create();
        $exec       = User::factory()->create(['person_id' => $execPerson->id]);
        $this->project->members()->create([
            'person_id' => $execPerson->id,
            'role'      => ProjectRole::Executive->value,
        ]);

        $this->actingAs($exec)
            ->postJson($this->indexUrl(), $this->validPayload())
            ->assertForbidden();
    }

    public function test_senior_user_can_create_quality_entry(): void
    {
        $suPerson = Person::factory()->create();
        $su       = User::factory()->create(['person_id' => $suPerson->id]);
        $this->project->members()->create([
            'person_id' => $suPerson->id,
            'role'      => ProjectRole::SeniorUser->value,
        ]);

        $this->actingAs($su)
            ->postJson($this->indexUrl(), $this->validPayload())
            ->assertCreated();
    }

    public function test_store_requires_product_name(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['product_name' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_name');
    }

    public function test_store_rejects_non_string_product_name(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['product_name' => ['not', 'a', 'string']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_name');
    }

    public function test_store_rejects_product_name_over_max_length(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['product_name' => str_repeat('a', 256)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_name');
    }

    public function test_store_requires_quality_method(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['quality_method' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quality_method');
    }

    public function test_store_rejects_invalid_quality_method(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['quality_method' => 'not-a-method']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quality_method');
    }

    public function test_store_rejects_invalid_planned_date(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['planned_date' => 'not-a-date']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('planned_date');
    }

    public function test_store_rejects_non_integer_stage_id(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['stage_id' => 'not-an-integer']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stage_id');
    }

    public function test_store_rejects_nonexistent_stage_id(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['stage_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stage_id');
    }

    public function test_store_creates_entry_with_valid_stage_id(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id]);

        $this->postJson($this->indexUrl(), $this->validPayload(['stage_id' => $stage->id]))
            ->assertCreated()
            ->assertJsonPath('data.stage_id', $stage->id);
    }

    public function test_show_returns_entry(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->getJson($this->entryUrl($entry))
            ->assertOk()
            ->assertJsonPath('data.id', $entry->id);
    }

    public function test_update_edits_entry(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['product_name' => 'Updated Product'])
            ->assertOk()
            ->assertJsonPath('data.product_name', 'Updated Product');
    }

    public function test_update_rejects_blank_product_name(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['product_name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_name');
    }

    public function test_update_rejects_non_string_product_name(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['product_name' => ['not', 'a', 'string']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_name');
    }

    public function test_update_rejects_blank_quality_method(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['quality_method' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quality_method');
    }

    public function test_update_rejects_invalid_quality_method(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['quality_method' => 'not-a-method'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quality_method');
    }

    public function test_update_edits_quality_method(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id'     => $this->project->id,
            'quality_method' => QualityMethod::Review,
        ]);

        $this->putJson($this->entryUrl($entry), ['quality_method' => QualityMethod::Audit->value])
            ->assertOk()
            ->assertJsonPath('data.quality_method', QualityMethod::Audit->value);
    }

    public function test_update_rejects_invalid_planned_date(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['planned_date' => 'not-a-date'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('planned_date');
    }

    public function test_update_edits_planned_date(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['planned_date' => '2026-08-01'])
            ->assertOk()
            ->assertJsonPath('data.planned_date', '2026-08-01');
    }

    public function test_update_rejects_invalid_actual_date(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['actual_date' => 'not-a-date'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('actual_date');
    }

    public function test_update_edits_actual_date(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['actual_date' => '2026-08-05'])
            ->assertOk()
            ->assertJsonPath('data.actual_date', '2026-08-05');
    }

    public function test_update_rejects_non_array_reviewers(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['reviewers' => 'not-an-array'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reviewers');
    }

    public function test_update_rejects_non_integer_reviewer(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['reviewers' => ['not-an-integer']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reviewers.0');
    }

    public function test_update_rejects_nonexistent_reviewer_id(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['reviewers' => [999999]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reviewers.0');
    }

    public function test_update_edits_reviewers_with_valid_person_ids(): void
    {
        $entry    = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);
        $reviewer = Person::factory()->create();

        $this->putJson($this->entryUrl($entry), ['reviewers' => [$reviewer->id]])
            ->assertOk()
            ->assertJsonPath('data.reviewers.0', $reviewer->id);
    }

    public function test_update_rejects_invalid_result(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['result' => 'not-a-result'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('result');
    }

    public function test_update_edits_result(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['result' => QualityResult::Passed->value])
            ->assertOk()
            ->assertJsonPath('data.result', QualityResult::Passed->value);
    }

    public function test_update_rejects_non_string_issues_raised(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['issues_raised' => ['not', 'a', 'string']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('issues_raised');
    }

    public function test_update_edits_issues_raised(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['issues_raised' => 'Some issue description'])
            ->assertOk()
            ->assertJsonPath('data.issues_raised', 'Some issue description');
    }

    public function test_update_rejects_non_integer_sign_off_by(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['sign_off_by' => 'not-an-integer'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sign_off_by');
    }

    public function test_update_rejects_nonexistent_sign_off_by(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['sign_off_by' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sign_off_by');
    }

    public function test_update_edits_sign_off_by(): void
    {
        $entry    = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);
        $signedBy = Person::factory()->create();

        $this->putJson($this->entryUrl($entry), ['sign_off_by' => $signedBy->id])
            ->assertOk()
            ->assertJsonPath('data.sign_off_by.id', $signedBy->id);
    }

    public function test_update_rejects_invalid_sign_off_at(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['sign_off_at' => 'not-a-date'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sign_off_at');
    }

    public function test_update_edits_sign_off_at(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['sign_off_at' => '2026-08-10'])
            ->assertOk()
            ->assertJsonPath('data.sign_off_at', '2026-08-10T00:00:00.000000Z');
    }

    public function test_update_rejects_non_integer_stage_id(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['stage_id' => 'not-an-integer'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stage_id');
    }

    public function test_update_rejects_nonexistent_stage_id(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['stage_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stage_id');
    }

    public function test_update_edits_stage_id(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);
        $stage = Stage::factory()->create(['project_id' => $this->project->id]);

        $this->putJson($this->entryUrl($entry), ['stage_id' => $stage->id])
            ->assertOk()
            ->assertJsonPath('data.stage_id', $stage->id);
    }

    public function test_destroy_deletes_entry(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->deleteJson($this->entryUrl($entry))->assertNoContent();

        $this->assertDatabaseMissing('quality_register_entries', ['id' => $entry->id]);
    }
}
