<?php

namespace Tests\Feature;

use App\Models\DocumentVersion;
use App\Models\Person;
use App\Models\Project;
use App\Models\QaDocument;
use App\Services\Document\GarageStorageService;
use App\Services\Document\OnlyOfficeEditorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OnlyOfficeEditorServiceTest extends TestCase
{
    use RefreshDatabase;

    private OnlyOfficeEditorService $service;
    private QaDocument $document;
    private Person $person;

    protected function setUp(): void
    {
        parent::setUp();

        $this->person   = Person::factory()->create();
        $project        = Project::factory()->create(['created_by' => $this->person->id]);
        $this->document = QaDocument::factory()->create([
            'project_id' => $project->id,
            'created_by' => $this->person->id,
        ]);

        $this->mock(GarageStorageService::class)
            ->shouldReceive('put')
            ->andReturnNull();

        $this->service = app(OnlyOfficeEditorService::class);
    }

    // -------------------------------------------------------------------------
    // helpers
    // -------------------------------------------------------------------------

    private function ghostVersion(int $versionNumber = 1): DocumentVersion
    {
        return DocumentVersion::factory()->create([
            'document_id'     => $this->document->id,
            'version_number'  => $versionNumber,
            'onlyoffice_key'  => 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            'file_size_bytes' => 0,
            'created_by'      => $this->person->id,
        ]);
    }

    private function savedVersion(int $versionNumber = 1): DocumentVersion
    {
        return DocumentVersion::factory()->create([
            'document_id'     => $this->document->id,
            'version_number'  => $versionNumber,
            'onlyoffice_key'  => 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            'file_size_bytes' => 65_536,
            'created_by'      => $this->person->id,
        ]);
    }

    private function triggerCallback(DocumentVersion $version, array $extra = []): void
    {
        $this->service->handleCallback(
            $version->onlyoffice_key,
            array_merge(['key' => $version->onlyoffice_key], $extra),
        );
    }

    // -------------------------------------------------------------------------
    // ghost version cleanup (status 4 — no prior save)
    // -------------------------------------------------------------------------

    public function test_status4_deletes_ghost_version_when_no_save_occurred(): void
    {
        $version = $this->ghostVersion();

        $this->triggerCallback($version, ['status' => 4]);

        $this->assertDatabaseMissing('document_versions', ['id' => $version->id]);
    }

    public function test_status4_ghost_deletion_does_not_touch_document_current_version(): void
    {
        $previous = DocumentVersion::factory()->create([
            'document_id'     => $this->document->id,
            'version_number'  => 1,
            'file_size_bytes' => 1024,
            'created_by'      => $this->person->id,
        ]);
        $this->document->update(['current_version_id' => $previous->id]);

        $ghost = $this->ghostVersion(versionNumber: 2);

        $this->triggerCallback($ghost, ['status' => 4]);

        $this->assertDatabaseMissing('document_versions', ['id' => $ghost->id]);
        $this->assertDatabaseHas('document_versions', ['id' => $previous->id]);
        $this->assertSame($previous->id, $this->document->fresh()->current_version_id);
    }

    public function test_status4_ghost_deletion_leaves_document_with_null_current_version_when_no_prior_version(): void
    {
        $this->assertNull($this->document->current_version_id);

        $ghost = $this->ghostVersion();

        $this->triggerCallback($ghost, ['status' => 4]);

        $this->assertDatabaseMissing('document_versions', ['id' => $ghost->id]);
        $this->assertNull($this->document->fresh()->current_version_id);
    }

    // -------------------------------------------------------------------------
    // normal close after save (status 4 — file was saved during the session)
    // -------------------------------------------------------------------------

    public function test_status4_marks_closed_without_changes_after_a_prior_save(): void
    {
        $version = $this->savedVersion();
        $this->document->update(['current_version_id' => $version->id]);

        $this->triggerCallback($version, ['status' => 4]);

        $this->assertDatabaseHas('document_versions', [
            'id'                     => $version->id,
            'closed_without_changes' => true,
        ]);
    }

    public function test_status4_does_not_delete_version_that_has_saved_content(): void
    {
        $version = $this->savedVersion();
        $this->document->update(['current_version_id' => $version->id]);

        $this->triggerCallback($version, ['status' => 4]);

        $this->assertDatabaseHas('document_versions', ['id' => $version->id]);
    }

    // -------------------------------------------------------------------------
    // status 2 — save
    // -------------------------------------------------------------------------

    public function test_status2_updates_file_size_bytes_to_actual_content_length(): void
    {
        Http::fake(['*' => Http::response('fake-document-content', 200)]);

        $version = $this->ghostVersion();

        $this->triggerCallback($version, ['status' => 2, 'url' => 'https://onlyoffice/download/file']);

        $this->assertDatabaseHas('document_versions', [
            'id'              => $version->id,
            'file_size_bytes' => strlen('fake-document-content'),
        ]);
    }

    public function test_status2_sets_document_current_version_id(): void
    {
        Http::fake(['*' => Http::response('content', 200)]);

        $version = $this->ghostVersion();

        $this->triggerCallback($version, ['status' => 2, 'url' => 'https://onlyoffice/download/file']);

        $this->assertSame($version->id, $this->document->fresh()->current_version_id);
    }

    public function test_status6_updates_file_size_bytes_and_current_version_id(): void
    {
        Http::fake(['*' => Http::response('force-saved-content', 200)]);

        $version = $this->ghostVersion();

        $this->triggerCallback($version, ['status' => 6, 'url' => 'https://onlyoffice/download/force-save']);

        $this->assertDatabaseHas('document_versions', [
            'id'              => $version->id,
            'file_size_bytes' => strlen('force-saved-content'),
        ]);
        $this->assertSame($version->id, $this->document->fresh()->current_version_id);
    }

    public function test_status2_followed_by_status4_keeps_version_and_marks_it_closed(): void
    {
        Http::fake(['*' => Http::response('content', 200)]);

        $version = $this->ghostVersion();

        $this->triggerCallback($version, ['status' => 2, 'url' => 'https://onlyoffice/download/file']);

        $version = DocumentVersion::find($version->id); // reload with updated file_size_bytes

        $this->triggerCallback($version, ['status' => 4]);

        $this->assertDatabaseHas('document_versions', [
            'id'                     => $version->id,
            'closed_without_changes' => true,
        ]);
    }
}
