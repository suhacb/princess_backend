<?php

namespace Tests\Feature\Projects;

use App\Contracts\DocumentEditorDriver;
use App\Enums\ProjectRole;
use App\Enums\QaDocumentStatus;
use App\Models\Person;
use App\Models\Project;
use App\Models\QaDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlyOfficeEditorConfigControllerTest extends TestCase
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
    }

    private function url(): string
    {
        return "/api/projects/{$this->project->id}/qa-documents/{$this->document->id}/editor-config";
    }

    // -------------------------------------------------------------------------
    // happy path
    // -------------------------------------------------------------------------

    public function test_returns_editor_config_json(): void
    {
        $this->mock(DocumentEditorDriver::class)
            ->shouldReceive('openSession')
            ->once()
            ->andReturn(['document' => ['key' => 'uuid'], 'token' => 'jwt']);

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('document.key', 'uuid')
            ->assertJsonPath('token', 'jwt');
    }

    public function test_open_session_receives_correct_document_and_person(): void
    {
        $this->mock(DocumentEditorDriver::class)
            ->shouldReceive('openSession')
            ->withArgs(function (QaDocument $doc, Person $person) {
                return $doc->is($this->document) && $person->is($this->person);
            })
            ->once()
            ->andReturn(['token' => 'jwt']);

        $this->getJson($this->url())->assertOk();
    }

    // -------------------------------------------------------------------------
    // auth / policy
    // -------------------------------------------------------------------------

    public function test_forbidden_for_observer_role(): void
    {
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($observer)
            ->getJson($this->url())
            ->assertForbidden();
    }

    public function test_forbidden_for_non_member(): void
    {
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->url())
            ->assertForbidden();
    }

    public function test_forbidden_for_confirmed_document(): void
    {
        $this->document->update(['status' => QaDocumentStatus::Confirmed->value]);

        $this->getJson($this->url())->assertForbidden();
    }

    public function test_returns_404_for_document_from_another_project(): void
    {
        $other      = Project::factory()->create(['created_by' => $this->person->id]);
        $foreignDoc = QaDocument::factory()->create(['project_id' => $other->id, 'created_by' => $this->person->id]);

        $this->getJson("/api/projects/{$this->project->id}/qa-documents/{$foreignDoc->id}/editor-config")
            ->assertNotFound();
    }
}
