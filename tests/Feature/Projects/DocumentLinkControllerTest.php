<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\QaDocumentStatus;
use App\Enums\QaDocumentType;
use App\Models\Meeting;
use App\Models\Person;
use App\Models\Project;
use App\Models\QaDocument;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentLinkControllerTest extends TestCase
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

    private function linkUrl(QaDocument $doc): string
    {
        return "/api/projects/{$this->project->id}/qa-documents/{$doc->id}/link";
    }

    private function makeDocument(array $attributes = []): QaDocument
    {
        return QaDocument::factory()->create(array_merge([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ], $attributes));
    }

    // -------------------------------------------------------------------------
    // link
    // -------------------------------------------------------------------------

    public function test_link_sets_documentable_on_meeting_minutes(): void
    {
        $document = $this->makeDocument(['type' => QaDocumentType::MeetingMinutes->value]);
        $meeting  = Meeting::factory()->create(['project_id' => $this->project->id]);

        $this->postJson($this->linkUrl($document), [
            'documentable_type' => 'meeting',
            'documentable_id'   => $meeting->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.documentable.type', 'Meeting')
            ->assertJsonPath('data.documentable.id', $meeting->id);

        $this->assertDatabaseHas('qa_documents', [
            'id'                => $document->id,
            'documentable_type' => Meeting::class,
            'documentable_id'   => $meeting->id,
        ]);
    }

    public function test_link_sets_documentable_on_meeting_agenda(): void
    {
        $document = $this->makeDocument(['type' => QaDocumentType::MeetingAgenda->value]);
        $meeting  = Meeting::factory()->create(['project_id' => $this->project->id]);

        $this->postJson($this->linkUrl($document), [
            'documentable_type' => 'meeting',
            'documentable_id'   => $meeting->id,
        ])->assertOk();

        $this->assertDatabaseHas('qa_documents', [
            'id'                => $document->id,
            'documentable_type' => Meeting::class,
            'documentable_id'   => $meeting->id,
        ]);
    }

    public function test_link_sets_documentable_on_stage_plan(): void
    {
        $document = $this->makeDocument(['type' => QaDocumentType::StagePlan->value]);
        $stage    = Stage::factory()->create(['project_id' => $this->project->id]);

        $this->postJson($this->linkUrl($document), [
            'documentable_type' => 'stage',
            'documentable_id'   => $stage->id,
        ])->assertOk();

        $this->assertDatabaseHas('qa_documents', [
            'id'                => $document->id,
            'documentable_type' => Stage::class,
            'documentable_id'   => $stage->id,
        ]);
    }

    public function test_link_sets_documentable_on_project_initiation_document(): void
    {
        $document = $this->makeDocument(['type' => QaDocumentType::ProjectInitiationDocument->value]);

        $this->postJson($this->linkUrl($document), [
            'documentable_type' => 'project',
            'documentable_id'   => $this->project->id,
        ])->assertOk();

        $this->assertDatabaseHas('qa_documents', [
            'id'                => $document->id,
            'documentable_type' => Project::class,
            'documentable_id'   => $this->project->id,
        ]);
    }

    public function test_link_rejects_incompatible_document_type(): void
    {
        $document = $this->makeDocument(['type' => QaDocumentType::HighlightReport->value]);
        $meeting  = Meeting::factory()->create(['project_id' => $this->project->id]);

        $this->postJson($this->linkUrl($document), [
            'documentable_type' => 'meeting',
            'documentable_id'   => $meeting->id,
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('qa_documents', [
            'id'                => $document->id,
            'documentable_type' => Meeting::class,
        ]);
    }

    public function test_link_rejects_entity_from_different_project(): void
    {
        $otherProject = Project::factory()->create(['created_by' => $this->person->id]);
        $document     = $this->makeDocument(['type' => QaDocumentType::MeetingMinutes->value]);
        $meeting      = Meeting::factory()->create(['project_id' => $otherProject->id]);

        $this->postJson($this->linkUrl($document), [
            'documentable_type' => 'meeting',
            'documentable_id'   => $meeting->id,
        ])->assertUnprocessable();
    }

    public function test_link_rejects_nonexistent_entity(): void
    {
        $document = $this->makeDocument(['type' => QaDocumentType::MeetingMinutes->value]);

        $this->postJson($this->linkUrl($document), [
            'documentable_type' => 'meeting',
            'documentable_id'   => 999999,
        ])->assertUnprocessable();
    }

    public function test_link_clears_existing_link_on_target_entity(): void
    {
        $meeting   = Meeting::factory()->create(['project_id' => $this->project->id]);
        $oldDoc    = $this->makeDocument([
            'type'              => QaDocumentType::MeetingMinutes->value,
            'documentable_type' => Meeting::class,
            'documentable_id'   => $meeting->id,
        ]);
        $newDoc = $this->makeDocument(['type' => QaDocumentType::MeetingMinutes->value]);

        $this->postJson($this->linkUrl($newDoc), [
            'documentable_type' => 'meeting',
            'documentable_id'   => $meeting->id,
        ])->assertOk();

        $this->assertDatabaseHas('qa_documents', [
            'id'                => $newDoc->id,
            'documentable_type' => Meeting::class,
            'documentable_id'   => $meeting->id,
        ]);

        // Old document must be cleared.
        $this->assertDatabaseHas('qa_documents', [
            'id'                => $oldDoc->id,
            'documentable_type' => null,
            'documentable_id'   => null,
        ]);
    }

    public function test_link_requires_documentable_type(): void
    {
        $document = $this->makeDocument(['type' => QaDocumentType::MeetingMinutes->value]);
        $meeting  = Meeting::factory()->create(['project_id' => $this->project->id]);

        $this->postJson($this->linkUrl($document), ['documentable_id' => $meeting->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('documentable_type');
    }

    public function test_link_forbidden_for_non_member(): void
    {
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);
        $document = $this->makeDocument(['type' => QaDocumentType::MeetingMinutes->value]);
        $meeting  = Meeting::factory()->create(['project_id' => $this->project->id]);

        $this->actingAs($stranger)
            ->postJson($this->linkUrl($document), [
                'documentable_type' => 'meeting',
                'documentable_id'   => $meeting->id,
            ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // unlink
    // -------------------------------------------------------------------------

    public function test_unlink_clears_documentable(): void
    {
        $meeting  = Meeting::factory()->create(['project_id' => $this->project->id]);
        $document = $this->makeDocument([
            'type'              => QaDocumentType::MeetingMinutes->value,
            'documentable_type' => Meeting::class,
            'documentable_id'   => $meeting->id,
        ]);

        $this->deleteJson($this->linkUrl($document))
            ->assertNoContent();

        $this->assertDatabaseHas('qa_documents', [
            'id'                => $document->id,
            'documentable_type' => null,
            'documentable_id'   => null,
        ]);
    }

    public function test_unlink_on_already_unlinked_document_succeeds(): void
    {
        $document = $this->makeDocument(['type' => QaDocumentType::MeetingMinutes->value]);

        $this->deleteJson($this->linkUrl($document))
            ->assertNoContent();
    }

    public function test_unlink_forbidden_for_non_member(): void
    {
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);
        $meeting  = Meeting::factory()->create(['project_id' => $this->project->id]);
        $document = $this->makeDocument([
            'type'              => QaDocumentType::MeetingMinutes->value,
            'documentable_type' => Meeting::class,
            'documentable_id'   => $meeting->id,
        ]);

        $this->actingAs($stranger)
            ->deleteJson($this->linkUrl($document))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // entity show includes document key
    // -------------------------------------------------------------------------

    public function test_meeting_show_includes_document_when_linked(): void
    {
        $meeting  = Meeting::factory()->create(['project_id' => $this->project->id]);
        $document = $this->makeDocument([
            'type'              => QaDocumentType::MeetingMinutes->value,
            'documentable_type' => Meeting::class,
            'documentable_id'   => $meeting->id,
        ]);

        $this->getJson("/api/projects/{$this->project->id}/meetings/{$meeting->id}")
            ->assertOk()
            ->assertJsonPath('data.document.id', $document->id)
            ->assertJsonPath('data.document.type', QaDocumentType::MeetingMinutes->value);
    }

    public function test_meeting_show_includes_null_document_when_not_linked(): void
    {
        $meeting = Meeting::factory()->create(['project_id' => $this->project->id]);

        $this->getJson("/api/projects/{$this->project->id}/meetings/{$meeting->id}")
            ->assertOk()
            ->assertJsonPath('data.document', null);
    }
}
