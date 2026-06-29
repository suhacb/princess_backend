<?php

namespace Tests\Feature\Projects;

use App\Contracts\DocumentStorageDriver;
use App\Enums\ProjectRole;
use App\Enums\QaDocumentStatus;
use App\Models\DocumentVersion;
use App\Models\Person;
use App\Models\Project;
use App\Models\QaDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DocumentVersionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Person $person;
    private Project $project;
    private QaDocument $document;

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

        $this->document = QaDocument::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);

        $this->mock(DocumentStorageDriver::class);
    }

    private function indexUrl(): string
    {
        return "/api/projects/{$this->project->id}/qa-documents/{$this->document->id}/versions";
    }

    private function revertUrl(DocumentVersion $version): string
    {
        return "/api/projects/{$this->project->id}/qa-documents/{$this->document->id}/versions/{$version->id}/revert";
    }

    private function uploadUrl(): string
    {
        return "/api/projects/{$this->project->id}/qa-documents/{$this->document->id}/upload";
    }

    private function downloadUrl(?int $versionId = null): string
    {
        $url = "/api/projects/{$this->project->id}/qa-documents/{$this->document->id}/download";
        return $versionId !== null ? "{$url}?version={$versionId}" : $url;
    }

    private function fakeDocx(string $name = 'plan.docx'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 512, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    private function makeVersion(array $attributes = []): DocumentVersion
    {
        return DocumentVersion::factory()->create(array_merge([
            'document_id' => $this->document->id,
            'created_by'  => $this->person->id,
        ], $attributes));
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_returns_versions_ordered_by_version_number(): void
    {
        $v2 = $this->makeVersion(['version_number' => 2, 's3_key' => 'documents/1/versions/2/doc.docx']);
        $v1 = $this->makeVersion(['version_number' => 1, 's3_key' => 'documents/1/versions/1/doc.docx']);

        $this->getJson($this->indexUrl())
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.version_number', 1)
            ->assertJsonPath('data.1.version_number', 2)
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_index_returns_empty_for_document_without_versions(): void
    {
        $this->getJson($this->indexUrl())
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_index_forbidden_for_non_member(): void
    {
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->indexUrl())
            ->assertForbidden();
    }

    public function test_index_returns_404_for_document_from_another_project(): void
    {
        $other = Project::factory()->create(['created_by' => $this->person->id]);
        $foreignDoc = QaDocument::factory()->create(['project_id' => $other->id, 'created_by' => $this->person->id]);

        $this->getJson("/api/projects/{$this->project->id}/qa-documents/{$foreignDoc->id}/versions")
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // revert
    // -------------------------------------------------------------------------

    public function test_revert_creates_new_version(): void
    {
        $v1 = $this->makeVersion([
            'version_number'  => 1,
            's3_key'          => 'documents/1/versions/1/plan.docx',
            'file_name'       => 'plan.docx',
            'file_size_bytes' => 2048,
        ]);

        $this->mock(DocumentStorageDriver::class)
            ->shouldReceive('copy')
            ->once();

        $response = $this->postJson($this->revertUrl($v1))
            ->assertCreated()
            ->assertJsonPath('data.version_number', 2)
            ->assertJsonPath('data.comment', 'Reverted to v1')
            ->assertJsonPath('data.file_name', 'plan.docx');

        $this->assertDatabaseHas('document_versions', [
            'document_id'    => $this->document->id,
            'version_number' => 2,
            'comment'        => 'Reverted to v1',
            'created_by'     => $this->person->id,
        ]);
    }

    public function test_revert_updates_current_version_id_on_document(): void
    {
        $v1 = $this->makeVersion(['version_number' => 1, 's3_key' => 'documents/1/versions/1/doc.docx']);

        $this->mock(DocumentStorageDriver::class)
            ->shouldReceive('copy')
            ->once();

        $response = $this->postJson($this->revertUrl($v1))->assertCreated();

        $newVersionId = $response->json('data.id');
        $this->assertDatabaseHas('qa_documents', [
            'id'                 => $this->document->id,
            'current_version_id' => $newVersionId,
        ]);
    }

    public function test_revert_copies_s3_object_to_new_key(): void
    {
        $v1 = $this->makeVersion([
            'version_number' => 1,
            's3_key'         => 'documents/1/versions/1/plan.docx',
            'file_name'      => 'plan.docx',
        ]);

        $this->mock(DocumentStorageDriver::class)
            ->shouldReceive('copy')
            ->withArgs(function (Project $project, string $src, string $dst) use ($v1) {
                return $project->is($this->project)
                    && $src === $v1->s3_key
                    && str_contains($dst, 'versions/2/');
            })
            ->once();

        $this->postJson($this->revertUrl($v1))->assertCreated();
    }

    public function test_revert_version_number_is_monotonically_incremented(): void
    {
        $v1 = $this->makeVersion(['version_number' => 1, 's3_key' => 'k1']);
        $v2 = $this->makeVersion(['version_number' => 2, 's3_key' => 'k2']);

        $this->mock(DocumentStorageDriver::class)->shouldReceive('copy')->once();

        $this->postJson($this->revertUrl($v1))
            ->assertCreated()
            ->assertJsonPath('data.version_number', 3);
    }

    public function test_revert_forbidden_for_read_only_role(): void
    {
        $v1 = $this->makeVersion(['version_number' => 1, 's3_key' => 'k1']);

        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($observer)
            ->postJson($this->revertUrl($v1))
            ->assertForbidden();
    }

    public function test_revert_forbidden_for_non_member(): void
    {
        $v1      = $this->makeVersion(['version_number' => 1, 's3_key' => 'k1']);
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->postJson($this->revertUrl($v1))
            ->assertForbidden();
    }

    public function test_revert_returns_404_for_document_from_another_project(): void
    {
        $other      = Project::factory()->create(['created_by' => $this->person->id]);
        $foreignDoc = QaDocument::factory()->create(['project_id' => $other->id, 'created_by' => $this->person->id]);
        $v1         = DocumentVersion::factory()->create(['document_id' => $foreignDoc->id, 'version_number' => 1, 's3_key' => 'k1', 'created_by' => $this->person->id]);

        $this->postJson("/api/projects/{$this->project->id}/qa-documents/{$foreignDoc->id}/versions/{$v1->id}/revert")
            ->assertNotFound();
    }

    public function test_revert_returns_404_for_version_from_another_document(): void
    {
        $otherDoc       = QaDocument::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->person->id]);
        $foreignVersion = $this->makeVersion(['version_number' => 1, 's3_key' => 'k1', 'document_id' => $otherDoc->id]);

        $this->postJson($this->revertUrl($foreignVersion))->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // immutability guard
    // -------------------------------------------------------------------------

    public function test_document_version_cannot_be_updated(): void
    {
        $v1 = $this->makeVersion(['version_number' => 1, 's3_key' => 'k1']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('immutable');

        $v1->comment = 'tampered';
        $v1->save();
    }

    public function test_document_version_cannot_be_deleted(): void
    {
        $v1 = $this->makeVersion(['version_number' => 1, 's3_key' => 'k1']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('immutable');

        $v1->delete();
    }

    // -------------------------------------------------------------------------
    // upload
    // -------------------------------------------------------------------------

    public function test_upload_creates_new_version(): void
    {
        $this->mock(DocumentStorageDriver::class)->shouldReceive('put')->once();

        $this->post($this->uploadUrl(), ['file' => $this->fakeDocx(), 'comment' => 'Initial upload'])
            ->assertCreated()
            ->assertJsonPath('data.version_number', 1)
            ->assertJsonPath('data.file_name', 'plan.docx')
            ->assertJsonPath('data.comment', 'Initial upload');
    }

    public function test_upload_updates_current_version_id(): void
    {
        $this->mock(DocumentStorageDriver::class)->shouldReceive('put')->once();

        $response = $this->post($this->uploadUrl(), ['file' => $this->fakeDocx()])->assertCreated();

        $this->assertDatabaseHas('qa_documents', [
            'id'                 => $this->document->id,
            'current_version_id' => $response->json('data.id'),
        ]);
    }

    public function test_upload_increments_version_number(): void
    {
        $this->makeVersion(['version_number' => 1, 's3_key' => 'k1']);
        $this->makeVersion(['version_number' => 2, 's3_key' => 'k2']);

        $this->mock(DocumentStorageDriver::class)->shouldReceive('put')->once();

        $this->post($this->uploadUrl(), ['file' => $this->fakeDocx()])
            ->assertCreated()
            ->assertJsonPath('data.version_number', 3);
    }

    public function test_upload_stores_key_with_uuid_path(): void
    {
        $this->mock(DocumentStorageDriver::class)
            ->shouldReceive('put')
            ->withArgs(function (Project $project, string $key) {
                return $project->is($this->project)
                    && preg_match('#^documents/\d+/versions/[0-9a-f-]{36}/original\.docx$#', $key) === 1;
            })
            ->once();

        $this->post($this->uploadUrl(), ['file' => $this->fakeDocx()])->assertCreated();
    }

    public function test_upload_rejected_for_confirmed_document(): void
    {
        $this->document->update(['status' => QaDocumentStatus::Confirmed->value]);

        $this->post($this->uploadUrl(), ['file' => $this->fakeDocx()])
            ->assertForbidden();
    }

    public function test_upload_rejected_for_invalid_file_type(): void
    {
        $pdf = UploadedFile::fake()->create('report.pdf', 512, 'application/pdf');

        $this->post($this->uploadUrl(), ['file' => $pdf])
            ->assertUnprocessable();
    }

    public function test_upload_forbidden_for_read_only_role(): void
    {
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($observer)
            ->post($this->uploadUrl(), ['file' => $this->fakeDocx()])
            ->assertForbidden();
    }

    public function test_upload_forbidden_for_non_member(): void
    {
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->post($this->uploadUrl(), ['file' => $this->fakeDocx()])
            ->assertForbidden();
    }

    public function test_upload_returns_404_for_document_from_another_project(): void
    {
        $other      = Project::factory()->create(['created_by' => $this->person->id]);
        $foreignDoc = QaDocument::factory()->create(['project_id' => $other->id, 'created_by' => $this->person->id]);

        $this->post("/api/projects/{$this->project->id}/qa-documents/{$foreignDoc->id}/upload", ['file' => $this->fakeDocx()])
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // download
    // -------------------------------------------------------------------------

    public function test_download_redirects_to_presigned_url(): void
    {
        $v1 = $this->makeVersion(['version_number' => 1, 's3_key' => 'documents/1/versions/uuid/original.docx']);
        $this->document->update(['current_version_id' => $v1->id]);

        $this->mock(DocumentStorageDriver::class)
            ->shouldReceive('temporaryUrl')
            ->once()
            ->andReturn('https://s3.example.com/presigned-url');

        $this->get($this->downloadUrl())
            ->assertRedirect('https://s3.example.com/presigned-url');
    }

    public function test_download_uses_specific_version_when_query_param_provided(): void
    {
        $v1 = $this->makeVersion(['version_number' => 1, 's3_key' => 'k1']);
        $v2 = $this->makeVersion(['version_number' => 2, 's3_key' => 'k2']);
        $this->document->update(['current_version_id' => $v2->id]);

        $this->mock(DocumentStorageDriver::class)
            ->shouldReceive('temporaryUrl')
            ->withArgs(function (Project $project, string $key) use ($v1) {
                return $key === $v1->s3_key;
            })
            ->once()
            ->andReturn('https://s3.example.com/v1-url');

        $this->get($this->downloadUrl($v1->id))
            ->assertRedirect('https://s3.example.com/v1-url');
    }

    public function test_download_returns_404_when_no_current_version(): void
    {
        $this->get($this->downloadUrl())->assertNotFound();
    }

    public function test_download_returns_404_when_version_from_another_document(): void
    {
        $otherDoc      = QaDocument::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->person->id]);
        $foreignVersion = DocumentVersion::factory()->create(['document_id' => $otherDoc->id, 'version_number' => 1, 's3_key' => 'k1', 'created_by' => $this->person->id]);

        $this->get($this->downloadUrl($foreignVersion->id))->assertNotFound();
    }

    public function test_download_forbidden_for_non_member(): void
    {
        $v1 = $this->makeVersion(['version_number' => 1, 's3_key' => 'k1']);
        $this->document->update(['current_version_id' => $v1->id]);

        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->get($this->downloadUrl())
            ->assertForbidden();
    }

    public function test_download_returns_404_for_document_from_another_project(): void
    {
        $other      = Project::factory()->create(['created_by' => $this->person->id]);
        $foreignDoc = QaDocument::factory()->create(['project_id' => $other->id, 'created_by' => $this->person->id]);

        $this->get("/api/projects/{$this->project->id}/qa-documents/{$foreignDoc->id}/download")
            ->assertNotFound();
    }
}
