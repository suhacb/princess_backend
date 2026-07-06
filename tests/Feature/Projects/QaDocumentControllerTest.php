<?php

namespace Tests\Feature\Projects;

use App\Enums\DocumentCategory;
use App\Enums\ProjectRole;
use App\Enums\QaDocumentStatus;
use App\Enums\QaDocumentType;
use App\Enums\RequirementStatus;
use App\Jobs\Document\ConvertDocumentJob;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\Meeting;
use App\Models\Person;
use App\Models\Project;
use App\Models\QaDocument;
use App\Models\Requirement;
use App\Models\User;
use App\Services\Document\GarageStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QaDocumentControllerTest extends TestCase
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
        return "/api/projects/{$this->project->id}/documents";
    }

    private function documentUrl(QaDocument $doc): string
    {
        return "/api/projects/{$this->project->id}/documents/{$doc->id}";
    }

    private function makeDocument(array $attributes = []): QaDocument
    {
        return QaDocument::factory()->create(array_merge([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ], $attributes));
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'type'  => QaDocumentType::RequirementsSpecification->value,
            'title' => 'System Requirements v1.0',
        ], $overrides);
    }

    private function assurancePerson(): array
    {
        $person = Person::factory()->create();
        $user   = User::factory()->create(['person_id' => $person->id]);
        $this->project->members()->create(['person_id' => $person->id, 'role' => ProjectRole::ProjectAssurance->value]);

        return [$person, $user];
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_lists_qa_documents(): void
    {
        $this->makeDocument();
        $this->makeDocument(['type' => QaDocumentType::TestSpecification->value]);

        $this->getJson($this->indexUrl())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_type(): void
    {
        $this->makeDocument(['type' => QaDocumentType::RequirementsSpecification->value]);
        $this->makeDocument(['type' => QaDocumentType::TestSpecification->value]);

        $this->getJson($this->indexUrl() . '?type=test_specification')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', QaDocumentType::TestSpecification->value);
    }

    public function test_index_filters_by_status(): void
    {
        $this->makeDocument(['status' => QaDocumentStatus::Draft->value]);
        $this->makeDocument(['status' => QaDocumentStatus::InReview->value]);

        $this->getJson($this->indexUrl() . '?status=in_review')
            ->assertOk()
            ->assertJsonCount(1, 'data');
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

    public function test_store_creates_qa_document(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload())
            ->assertCreated()
            ->assertJsonPath('data.type', QaDocumentType::RequirementsSpecification->value)
            ->assertJsonPath('data.status', QaDocumentStatus::Draft->value)
            ->assertJsonPath('data.title', 'System Requirements v1.0');

        $this->assertDatabaseHas('qa_documents', [
            'project_id' => $this->project->id,
            'type'       => QaDocumentType::RequirementsSpecification->value,
            'status'     => QaDocumentStatus::Draft->value,
            'created_by' => $this->person->id,
        ]);
    }

    public function test_store_with_linked_requirements(): void
    {
        $req1 = Requirement::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->person->id]);
        $req2 = Requirement::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->person->id, 'ref' => 'REQ-002']);

        $this->postJson($this->indexUrl(), $this->storePayload(['requirement_ids' => [$req1->id, $req2->id]]))
            ->assertCreated();

        $document = QaDocument::first();
        $this->assertCount(2, $document->requirements);
    }

    public function test_store_with_supersedes_link(): void
    {
        $old = $this->makeDocument(['status' => QaDocumentStatus::Confirmed->value]);

        $this->postJson($this->indexUrl(), $this->storePayload(['supersedes_id' => $old->id]))
            ->assertCreated()
            ->assertJsonPath('data.supersedes_id', $old->id);
    }

    public function test_store_rejects_supersedes_if_not_confirmed(): void
    {
        $draft = $this->makeDocument(['status' => QaDocumentStatus::Draft->value]);

        $this->postJson($this->indexUrl(), $this->storePayload(['supersedes_id' => $draft->id]))
            ->assertUnprocessable();
    }

    public function test_store_rejects_supersedes_from_another_project(): void
    {
        $other   = Project::factory()->create(['created_by' => $this->person->id]);
        $foreign = QaDocument::factory()->confirmed()->create(['project_id' => $other->id, 'created_by' => $this->person->id]);

        $this->postJson($this->indexUrl(), $this->storePayload(['supersedes_id' => $foreign->id]))
            ->assertUnprocessable();
    }

    public function test_store_rejects_requirement_ids_on_non_requirements_spec(): void
    {
        $req = Requirement::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->person->id]);

        $this->postJson($this->indexUrl(), [
            'type'            => QaDocumentType::TestSpecification->value,
            'title'           => 'Test spec',
            'requirement_ids' => [$req->id],
        ])->assertUnprocessable();
    }

    public function test_store_rejects_requirement_ids_from_another_project(): void
    {
        $other      = Project::factory()->create(['created_by' => $this->person->id]);
        $foreignReq = Requirement::factory()->create(['project_id' => $other->id, 'created_by' => $this->person->id]);

        $this->postJson($this->indexUrl(), $this->storePayload(['requirement_ids' => [$foreignReq->id]]))
            ->assertUnprocessable();
    }

    public function test_store_rejects_nonexistent_requirement_id(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['requirement_ids' => [999999]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('requirement_ids.0');
    }

    public function test_store_rejects_nonexistent_supersedes_id(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['supersedes_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('supersedes_id');
    }

    // -------------------------------------------------------------------------
    // store – validation
    // -------------------------------------------------------------------------

    public function test_store_requires_type(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['type' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_store_rejects_invalid_type(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['type' => 'bogus']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_store_requires_title(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['title' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_store_accepts_valid_version(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['version' => 'v1.0']))
            ->assertCreated()
            ->assertJsonPath('data.version', 'v1.0');
    }

    public function test_store_rejects_version_exceeding_max_length(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['version' => str_repeat('a', 51)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('version');
    }

    public function test_store_forbidden_for_read_only_role(): void
    {
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($observer)
            ->postJson($this->indexUrl(), $this->storePayload())
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_document(): void
    {
        $doc = $this->makeDocument();

        $this->getJson($this->documentUrl($doc))
            ->assertOk()
            ->assertJsonPath('data.id', $doc->id);
    }

    public function test_show_includes_versions_count(): void
    {
        $doc = $this->makeDocument();
        DocumentVersion::factory()->create(['document_id' => $doc->id, 'created_by' => $this->person->id, 'version_number' => 1]);
        DocumentVersion::factory()->create(['document_id' => $doc->id, 'created_by' => $this->person->id, 'version_number' => 2]);

        $this->getJson($this->documentUrl($doc))
            ->assertOk()
            ->assertJsonPath('data.versions_count', 2);
    }

    public function test_show_includes_current_version_creator(): void
    {
        $doc     = $this->makeDocument();
        $version = DocumentVersion::factory()->create([
            'document_id'    => $doc->id,
            'created_by'     => $this->person->id,
            'version_number' => 1,
        ]);
        $doc->update(['current_version_id' => $version->id]);

        $this->getJson($this->documentUrl($doc))
            ->assertOk()
            ->assertJsonPath('data.current_version.created_by.id', $this->person->id);
    }

    public function test_show_forbidden_for_non_member(): void
    {
        $doc      = $this->makeDocument();
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->documentUrl($doc))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_edits_draft_document(): void
    {
        $doc = $this->makeDocument(['title' => 'Original']);

        $this->putJson($this->documentUrl($doc), ['title' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated');
    }

    public function test_update_blocked_on_confirmed_document(): void
    {
        $doc = $this->makeDocument(['status' => QaDocumentStatus::Confirmed->value]);

        $this->putJson($this->documentUrl($doc), ['title' => 'Trying to edit confirmed'])
            ->assertUnprocessable();
    }

    public function test_update_blocked_on_superseded_document(): void
    {
        $doc = $this->makeDocument(['status' => QaDocumentStatus::Superseded->value]);

        $this->putJson($this->documentUrl($doc), ['title' => 'Trying to edit superseded'])
            ->assertUnprocessable();
    }

    public function test_update_rejects_null_title(): void
    {
        $doc = $this->makeDocument(['title' => 'Original']);

        $this->putJson($this->documentUrl($doc), ['title' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_update_requires_documentable_id_when_documentable_type_present(): void
    {
        $doc = $this->makeDocument();

        $this->putJson($this->documentUrl($doc), ['documentable_type' => 'meeting'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documentable_id');
    }

    public function test_update_forbidden_for_read_only_role(): void
    {
        $doc            = $this->makeDocument();
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($observer)
            ->putJson($this->documentUrl($doc), ['title' => 'Hijacked'])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_draft_document(): void
    {
        $doc = $this->makeDocument(['status' => QaDocumentStatus::Draft->value]);

        $this->deleteJson($this->documentUrl($doc))->assertNoContent();
        $this->assertSoftDeleted('qa_documents', ['id' => $doc->id]);
    }

    public function test_destroy_forbidden_on_non_draft_document(): void
    {
        $doc = $this->makeDocument(['status' => QaDocumentStatus::InReview->value]);

        $this->deleteJson($this->documentUrl($doc))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // sendForReview
    // -------------------------------------------------------------------------

    public function test_send_for_review_transitions_draft_to_in_review(): void
    {
        $doc = $this->makeDocument(['status' => QaDocumentStatus::Draft->value]);

        $this->postJson("/api/projects/{$this->project->id}/documents/{$doc->id}/send-for-review")
            ->assertOk()
            ->assertJsonPath('data.status', QaDocumentStatus::InReview->value);
    }

    public function test_send_for_review_returns_409_if_not_draft(): void
    {
        $doc = $this->makeDocument(['status' => QaDocumentStatus::InReview->value]);

        $this->postJson("/api/projects/{$this->project->id}/documents/{$doc->id}/send-for-review")
            ->assertStatus(409);
    }

    public function test_send_for_review_forbidden_for_read_only_role(): void
    {
        $doc            = $this->makeDocument();
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($observer)
            ->postJson("/api/projects/{$this->project->id}/documents/{$doc->id}/send-for-review")
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // reject
    // -------------------------------------------------------------------------

    public function test_reject_transitions_in_review_to_draft(): void
    {
        $doc = $this->makeDocument(['status' => QaDocumentStatus::InReview->value]);

        [$assurancePerson, $assurance] = $this->assurancePerson();

        $this->actingAs($assurance)
            ->postJson("/api/projects/{$this->project->id}/documents/{$doc->id}/reject", [
                'review_notes' => 'Needs more detail in section 3.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', QaDocumentStatus::Draft->value)
            ->assertJsonPath('data.review_notes', 'Needs more detail in section 3.');

        $this->assertDatabaseHas('qa_documents', [
            'id'          => $doc->id,
            'reviewed_by' => $assurancePerson->id,
        ]);
    }

    public function test_reject_requires_review_notes(): void
    {
        $doc = $this->makeDocument(['status' => QaDocumentStatus::InReview->value]);

        [$assurancePerson, $assurance] = $this->assurancePerson();

        $this->actingAs($assurance)
            ->postJson("/api/projects/{$this->project->id}/documents/{$doc->id}/reject", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('review_notes');
    }

    public function test_reject_returns_409_if_not_in_review(): void
    {
        $doc = $this->makeDocument(['status' => QaDocumentStatus::Draft->value]);

        [$assurancePerson, $assurance] = $this->assurancePerson();

        $this->actingAs($assurance)
            ->postJson("/api/projects/{$this->project->id}/documents/{$doc->id}/reject", [
                'review_notes' => 'Not applicable',
            ])
            ->assertStatus(409);
    }

    // -------------------------------------------------------------------------
    // confirm
    // -------------------------------------------------------------------------

    public function test_confirm_transitions_in_review_to_confirmed(): void
    {
        $doc = $this->makeDocument(['status' => QaDocumentStatus::InReview->value]);

        [$assurancePerson, $assurance] = $this->assurancePerson();

        $this->actingAs($assurance)
            ->postJson("/api/projects/{$this->project->id}/documents/{$doc->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', QaDocumentStatus::Confirmed->value);

        $this->assertDatabaseHas('qa_documents', [
            'id'           => $doc->id,
            'status'       => QaDocumentStatus::Confirmed->value,
            'confirmed_by' => $assurancePerson->id,
            'reviewed_by'  => $assurancePerson->id,
        ]);
    }

    public function test_confirm_blocked_when_confirmer_is_also_author(): void
    {
        // $this->person created the document; $this->user is the same person as PM
        $doc = $this->makeDocument(['status' => QaDocumentStatus::InReview->value]);

        $this->postJson("/api/projects/{$this->project->id}/documents/{$doc->id}/confirm")
            ->assertForbidden();
    }

    public function test_confirm_allowed_for_board_and_pm(): void
    {
        foreach ([ProjectRole::Executive, ProjectRole::SeniorUser, ProjectRole::SeniorSupplier, ProjectRole::ProjectManager] as $role) {
            $memberPerson = Person::factory()->create();
            $memberUser   = User::factory()->create(['person_id' => $memberPerson->id]);
            $this->project->members()->create(['person_id' => $memberPerson->id, 'role' => $role->value]);

            $doc = $this->makeDocument(['status' => QaDocumentStatus::InReview->value]);

            $this->actingAs($memberUser)
                ->postJson("/api/projects/{$this->project->id}/documents/{$doc->id}/confirm")
                ->assertOk("Role {$role->value} should be able to confirm");
        }
    }

    public function test_confirm_returns_409_if_not_in_review(): void
    {
        $doc = $this->makeDocument(['status' => QaDocumentStatus::Draft->value]);

        [$assurancePerson, $assurance] = $this->assurancePerson();

        $this->actingAs($assurance)
            ->postJson("/api/projects/{$this->project->id}/documents/{$doc->id}/confirm")
            ->assertStatus(409);
    }

    public function test_confirm_cascades_reviewed_requirements_to_approved(): void
    {
        $req1 = Requirement::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
            'status'     => RequirementStatus::Reviewed->value,
        ]);
        $req2 = Requirement::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
            'ref'        => 'REQ-002',
            'status'     => RequirementStatus::Draft->value,
        ]);

        $doc = $this->makeDocument([
            'type'   => QaDocumentType::RequirementsSpecification->value,
            'status' => QaDocumentStatus::InReview->value,
        ]);
        $doc->requirements()->sync([$req1->id, $req2->id]);

        [$assurancePerson, $assurance] = $this->assurancePerson();

        $this->actingAs($assurance)
            ->postJson("/api/projects/{$this->project->id}/documents/{$doc->id}/confirm")
            ->assertOk();

        // Reviewed requirement gets approved
        $this->assertDatabaseHas('requirements', [
            'id'     => $req1->id,
            'status' => RequirementStatus::Approved->value,
        ]);
        // Draft requirement stays draft
        $this->assertDatabaseHas('requirements', [
            'id'     => $req2->id,
            'status' => RequirementStatus::Draft->value,
        ]);
    }

    public function test_confirm_does_not_cascade_requirements_for_non_requirements_spec(): void
    {
        $req = Requirement::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
            'status'     => RequirementStatus::Reviewed->value,
        ]);

        $doc = $this->makeDocument([
            'type'   => QaDocumentType::TestSpecification->value,
            'status' => QaDocumentStatus::InReview->value,
        ]);

        [$assurancePerson, $assurance] = $this->assurancePerson();

        $this->actingAs($assurance)
            ->postJson("/api/projects/{$this->project->id}/documents/{$doc->id}/confirm")
            ->assertOk();

        // Requirement must NOT be touched — doc type is test_specification
        $this->assertDatabaseHas('requirements', [
            'id'     => $req->id,
            'status' => RequirementStatus::Reviewed->value,
        ]);
    }

    public function test_confirm_marks_superseded_document_as_superseded(): void
    {
        $old = $this->makeDocument(['status' => QaDocumentStatus::Confirmed->value]);
        $new = $this->makeDocument([
            'status'       => QaDocumentStatus::InReview->value,
            'supersedes_id' => $old->id,
        ]);

        [$assurancePerson, $assurance] = $this->assurancePerson();

        $this->actingAs($assurance)
            ->postJson("/api/projects/{$this->project->id}/documents/{$new->id}/confirm")
            ->assertOk();

        $this->assertDatabaseHas('qa_documents', [
            'id'     => $old->id,
            'status' => QaDocumentStatus::Superseded->value,
        ]);
    }

    public function test_confirm_forbidden_for_observer(): void
    {
        $doc            = $this->makeDocument(['status' => QaDocumentStatus::InReview->value]);
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($observer)
            ->postJson("/api/projects/{$this->project->id}/documents/{$doc->id}/confirm")
            ->assertForbidden();
    }

    public function test_confirm_dispatches_convert_job_when_document_has_version(): void
    {
        Queue::fake();

        $doc = $this->makeDocument(['status' => QaDocumentStatus::InReview->value]);
        $version = DocumentVersion::factory()->create([
            'document_id' => $doc->id,
            'created_by'  => $this->person->id,
        ]);
        $doc->update(['current_version_id' => $version->id]);

        [, $assurance] = $this->assurancePerson();

        $this->actingAs($assurance)
            ->postJson("/api/projects/{$this->project->id}/documents/{$doc->id}/confirm")
            ->assertOk();

        Queue::assertPushed(ConvertDocumentJob::class, function ($job) use ($version) {
            return $job->version->id === $version->id;
        });
    }

    public function test_confirm_does_not_dispatch_convert_job_when_document_has_no_version(): void
    {
        Queue::fake();

        $doc = $this->makeDocument(['status' => QaDocumentStatus::InReview->value]);

        [, $assurance] = $this->assurancePerson();

        $this->actingAs($assurance)
            ->postJson("/api/projects/{$this->project->id}/documents/{$doc->id}/confirm")
            ->assertOk();

        Queue::assertNotPushed(ConvertDocumentJob::class);
    }

    // -------------------------------------------------------------------------
    // taxonomy — category auto-population and filtering
    // -------------------------------------------------------------------------

    public function test_store_auto_populates_category_from_type(): void
    {
        $this->postJson($this->indexUrl(), [
            'type'  => QaDocumentType::HighlightReport->value,
            'title' => 'June Highlight',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', QaDocumentType::HighlightReport->value)
            ->assertJsonPath('data.category', DocumentCategory::Reporting->value);

        $this->assertDatabaseHas('qa_documents', [
            'type'     => QaDocumentType::HighlightReport->value,
            'category' => DocumentCategory::Reporting->value,
        ]);
    }

    public function test_index_filters_by_category(): void
    {
        $this->makeDocument(['type' => QaDocumentType::HighlightReport->value, 'category' => DocumentCategory::Reporting->value]);
        $this->makeDocument(['type' => QaDocumentType::RequirementsSpecification->value, 'category' => DocumentCategory::Qa->value]);
        $this->makeDocument(['type' => QaDocumentType::StagePlan->value, 'category' => DocumentCategory::Planning->value]);

        $this->getJson($this->indexUrl() . '?category=reporting')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', DocumentCategory::Reporting->value);
    }

    public function test_store_all_new_type_values_are_accepted(): void
    {
        $newTypes = [
            QaDocumentType::ProjectBrief,
            QaDocumentType::MeetingAgenda,
            QaDocumentType::MeetingMinutes,
            QaDocumentType::RiskRegister,
            QaDocumentType::General,
        ];

        foreach ($newTypes as $type) {
            $this->postJson($this->indexUrl(), ['type' => $type->value, 'title' => "Doc for {$type->value}"])
                ->assertCreated("Type {$type->value} should be accepted");
        }
    }

    // -------------------------------------------------------------------------
    // taxonomy — metadata
    // -------------------------------------------------------------------------

    public function test_store_persists_structured_metadata(): void
    {
        $this->postJson($this->indexUrl(), [
            'type'     => QaDocumentType::HighlightReport->value,
            'title'    => 'June Highlight',
            'metadata' => [
                'reporting_period_start' => '2026-06-01',
                'reporting_period_end'   => '2026-06-30',
                'board_actions_required' => true,
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.metadata.reporting_period_start', '2026-06-01')
            ->assertJsonPath('data.metadata.board_actions_required', true);
    }

    public function test_store_rejects_invalid_metadata_date(): void
    {
        $this->postJson($this->indexUrl(), [
            'type'     => QaDocumentType::HighlightReport->value,
            'title'    => 'June Highlight',
            'metadata' => [
                'reporting_period_start' => 'not-a-date',
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('metadata.reporting_period_start');
    }

    public function test_store_null_metadata_stored_as_null(): void
    {
        $this->postJson($this->indexUrl(), [
            'type'  => QaDocumentType::General->value,
            'title' => 'Generic doc',
        ])
            ->assertCreated()
            ->assertJsonPath('data.metadata', null);
    }

    public function test_update_persists_metadata(): void
    {
        $doc = $this->makeDocument([
            'type'     => QaDocumentType::HighlightReport->value,
            'category' => 'reporting',
            'metadata' => null,
        ]);

        $this->putJson($this->documentUrl($doc), [
            'metadata' => [
                'reporting_period_start' => '2026-07-01',
                'reporting_period_end'   => '2026-07-31',
                'board_actions_required' => false,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.metadata.reporting_period_start', '2026-07-01')
            ->assertJsonPath('data.metadata.board_actions_required', false);
    }

    public function test_update_clears_metadata(): void
    {
        $doc = $this->makeDocument([
            'type'     => QaDocumentType::HighlightReport->value,
            'category' => 'reporting',
            'metadata' => ['reporting_period_start' => '2026-07-01'],
        ]);

        $this->putJson($this->documentUrl($doc), ['metadata' => null])
            ->assertOk()
            ->assertJsonPath('data.metadata', null);
    }

    // -------------------------------------------------------------------------
    // taxonomy — documentable link
    // -------------------------------------------------------------------------

    public function test_store_links_documentable_meeting(): void
    {
        $meeting = Meeting::factory()->create(['project_id' => $this->project->id]);

        $this->postJson($this->indexUrl(), [
            'type'             => QaDocumentType::MeetingMinutes->value,
            'title'            => 'Board meeting minutes',
            'documentable_type' => 'meeting',
            'documentable_id'   => $meeting->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.documentable', null); // not loaded on store index

        $this->assertDatabaseHas('qa_documents', [
            'documentable_type' => \App\Models\Meeting::class,
            'documentable_id'   => $meeting->id,
        ]);
    }

    public function test_store_rejects_unknown_documentable_type(): void
    {
        $this->postJson($this->indexUrl(), [
            'type'             => QaDocumentType::MeetingMinutes->value,
            'title'            => 'Bad link',
            'documentable_type' => 'bogus_model',
            'documentable_id'   => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documentable_type');
    }

    public function test_store_requires_documentable_id_when_documentable_type_present(): void
    {
        $this->postJson($this->indexUrl(), [
            'type'              => QaDocumentType::MeetingMinutes->value,
            'title'             => 'Missing documentable id',
            'documentable_type' => 'meeting',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documentable_id');
    }

    // -------------------------------------------------------------------------
    // template integration
    // -------------------------------------------------------------------------

    public function test_store_applies_matching_template_as_version_1(): void
    {
        DocumentTemplate::create([
            'project_id' => $this->project->id,
            'name'       => 'Requirements Template',
            'category'   => DocumentCategory::Qa->value,
            'type'       => QaDocumentType::RequirementsSpecification->value,
            's3_key'     => 'templates/1/original.docx',
            'settings'   => [],
            'created_by' => $this->person->id,
        ]);

        $storage = $this->createMock(GarageStorageService::class);
        $storage->method('copy');
        $storage->method('size')->willReturn(1024);
        $this->app->instance(GarageStorageService::class, $storage);

        $response = $this->postJson($this->indexUrl(), $this->storePayload([
            'type' => QaDocumentType::RequirementsSpecification->value,
        ]))->assertCreated();

        $docId = $response->json('data.id');

        $this->assertDatabaseHas('document_versions', [
            'document_id'    => $docId,
            'version_number' => 1,
            'comment'        => 'Applied from template',
        ]);

        $this->assertDatabaseHas('qa_documents', [
            'id'                 => $docId,
            'current_version_id' => DocumentVersion::where('document_id', $docId)->value('id'),
        ]);
    }

    public function test_store_succeeds_even_when_no_matching_template_exists(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload())
            ->assertCreated();

        $this->assertDatabaseMissing('document_versions', [
            'comment' => 'Applied from template',
        ]);
    }
}
