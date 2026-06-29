<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Models\DocumentTemplate;
use App\Models\Person;
use App\Models\Project;
use App\Models\User;
use App\Services\Document\GarageStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DocumentTemplateControllerTest extends TestCase
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

    private function indexUrl(array $params = []): string
    {
        $url = "/api/projects/{$this->project->id}/templates";
        if ($params) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    }

    private function storeUrl(): string
    {
        return "/api/projects/{$this->project->id}/templates";
    }

    private function updateUrl(DocumentTemplate $template): string
    {
        return "/api/projects/{$this->project->id}/templates/{$template->id}";
    }

    private function destroyUrl(DocumentTemplate $template, array $params = []): string
    {
        $url = "/api/projects/{$this->project->id}/templates/{$template->id}";
        if ($params) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    }

    private function uploadUrl(DocumentTemplate $template): string
    {
        return "/api/projects/{$this->project->id}/templates/{$template->id}/upload";
    }

    private function makeTemplate(array $overrides = []): DocumentTemplate
    {
        return DocumentTemplate::create(array_merge([
            'project_id' => $this->project->id,
            'parent_id'  => null,
            'name'       => 'Base Template',
            'category'   => null,
            'type'       => null,
            's3_key'     => null,
            'settings'   => [],
            'created_by' => $this->person->id,
        ], $overrides));
    }

    private function fakeDocx(): UploadedFile
    {
        return UploadedFile::fake()->create('template.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    // =========================================================================
    // GET /projects/{project}/templates
    // =========================================================================

    public function test_index_returns_tree_for_project_manager(): void
    {
        $this->makeTemplate(['name' => 'Root']);

        $this->getJson($this->indexUrl())
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Root');
    }

    public function test_index_includes_global_templates(): void
    {
        $this->makeTemplate(['name' => 'Project Root']);
        DocumentTemplate::create([
            'project_id' => null,
            'name'       => 'Global Root',
            'category'   => null,
            'type'       => null,
            's3_key'     => null,
            'settings'   => [],
            'created_by' => $this->person->id,
        ]);

        $names = collect($this->getJson($this->indexUrl())->assertOk()->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Project Root'));
        $this->assertTrue($names->contains('Global Root'));
    }

    public function test_index_nests_children_under_parent(): void
    {
        $parent = $this->makeTemplate(['name' => 'Parent']);
        $this->makeTemplate(['name' => 'Child', 'category' => 'reporting', 'parent_id' => $parent->id]);

        $data = $this->getJson($this->indexUrl())->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('Parent', $data[0]['name']);
        $this->assertCount(1, $data[0]['children']);
        $this->assertSame('Child', $data[0]['children'][0]['name']);
    }

    public function test_index_filters_by_category(): void
    {
        $this->makeTemplate(['name' => 'Reports', 'category' => 'reporting']);
        $this->makeTemplate(['name' => 'Plans', 'category' => 'planning']);

        $data = $this->getJson($this->indexUrl(['category' => 'reporting']))->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('Reports', $data[0]['name']);
    }

    public function test_index_filters_by_type(): void
    {
        $this->makeTemplate(['name' => 'Highlight', 'category' => 'reporting', 'type' => 'highlight_report']);
        $this->makeTemplate(['name' => 'Checkpoint', 'category' => 'reporting', 'type' => 'checkpoint_report']);

        $data = $this->getJson($this->indexUrl(['type' => 'highlight_report']))->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('Highlight', $data[0]['name']);
    }

    public function test_index_forbidden_for_non_member(): void
    {
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->indexUrl())
            ->assertForbidden();
    }

    public function test_index_accessible_for_observer(): void
    {
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($observer)
            ->getJson($this->indexUrl())
            ->assertOk();
    }

    // =========================================================================
    // POST /projects/{project}/templates
    // =========================================================================

    public function test_store_creates_template(): void
    {
        $this->postJson($this->storeUrl(), ['name' => 'New Root', 'settings' => ['font' => 'Arial']])
            ->assertCreated()
            ->assertJsonPath('data.name', 'New Root')
            ->assertJsonPath('data.settings.font', 'Arial')
            ->assertJsonPath('data.project_id', $this->project->id);
    }

    public function test_store_assigns_project_id(): void
    {
        $this->postJson($this->storeUrl(), ['name' => 'My Template'])
            ->assertCreated()
            ->assertJsonPath('data.project_id', $this->project->id);
    }

    public function test_store_persists_to_database(): void
    {
        $this->postJson($this->storeUrl(), ['name' => 'DB Template'])->assertCreated();

        $this->assertDatabaseHas('document_templates', [
            'name'       => 'DB Template',
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);
    }

    public function test_store_validates_name_required(): void
    {
        $this->postJson($this->storeUrl(), [])->assertUnprocessable();
    }

    public function test_store_forbidden_for_observer(): void
    {
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($observer)
            ->postJson($this->storeUrl(), ['name' => 'x'])
            ->assertForbidden();
    }

    public function test_store_creates_child_with_parent_id(): void
    {
        $parent = $this->makeTemplate(['name' => 'Parent']);

        $this->postJson($this->storeUrl(), [
            'name'      => 'Child',
            'category'  => 'reporting',
            'parent_id' => $parent->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.parent_id', $parent->id);
    }

    // =========================================================================
    // PUT /projects/{project}/templates/{template}
    // =========================================================================

    public function test_update_changes_name(): void
    {
        $template = $this->makeTemplate(['name' => 'Old Name']);

        $this->putJson($this->updateUrl($template), ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_update_merges_settings(): void
    {
        $template = $this->makeTemplate(['settings' => ['font' => 'Arial']]);

        $this->putJson($this->updateUrl($template), ['settings' => ['margin' => 20]])
            ->assertOk()
            ->assertJsonPath('data.settings.margin', 20);
    }

    public function test_update_returns_404_for_template_from_another_project(): void
    {
        $other   = Project::factory()->create(['created_by' => $this->person->id]);
        $foreign = DocumentTemplate::create([
            'project_id' => $other->id,
            'name'       => 'Foreign',
            'settings'   => [],
            'created_by' => $this->person->id,
        ]);

        $this->putJson($this->updateUrl($foreign), ['name' => 'x'])
            ->assertNotFound();
    }

    // =========================================================================
    // DELETE /projects/{project}/templates/{template}
    // =========================================================================

    public function test_destroy_soft_deletes_template(): void
    {
        $template = $this->makeTemplate();

        $this->deleteJson($this->destroyUrl($template))->assertNoContent();

        $this->assertSoftDeleted('document_templates', ['id' => $template->id]);
    }

    public function test_destroy_force_cascades_to_children(): void
    {
        $parent = $this->makeTemplate(['name' => 'Parent']);
        $child  = $this->makeTemplate(['name' => 'Child', 'category' => 'reporting', 'parent_id' => $parent->id]);

        $this->deleteJson($this->destroyUrl($parent, ['force' => true]))->assertNoContent();

        $this->assertDatabaseMissing('document_templates', ['id' => $parent->id]);
        $this->assertDatabaseMissing('document_templates', ['id' => $child->id]);
    }

    public function test_destroy_without_force_does_not_cascade(): void
    {
        $parent = $this->makeTemplate(['name' => 'Parent']);
        $child  = $this->makeTemplate(['name' => 'Child', 'category' => 'reporting', 'parent_id' => $parent->id]);

        $this->deleteJson($this->destroyUrl($parent))->assertNoContent();

        $this->assertSoftDeleted('document_templates', ['id' => $parent->id]);
        $this->assertDatabaseHas('document_templates', ['id' => $child->id, 'deleted_at' => null]);
    }

    public function test_destroy_forbidden_for_observer(): void
    {
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);
        $template = $this->makeTemplate();

        $this->actingAs($observer)
            ->deleteJson($this->destroyUrl($template))
            ->assertForbidden();
    }

    // =========================================================================
    // POST /projects/{project}/templates/{template}/upload
    // =========================================================================

    public function test_upload_stores_file_and_sets_s3_key(): void
    {
        $template = $this->makeTemplate();

        $this->mock(GarageStorageService::class)->shouldReceive('put')->once();

        $this->postJson($this->uploadUrl($template), ['file' => $this->fakeDocx()])
            ->assertOk()
            ->assertJsonPath('data.has_file', true);

        $this->assertDatabaseHas('document_templates', [
            'id'    => $template->id,
            's3_key' => "templates/{$template->id}/original.docx",
        ]);
    }

    public function test_upload_rejects_non_docx_file(): void
    {
        $template = $this->makeTemplate();
        $pdf      = UploadedFile::fake()->create('file.pdf', 100, 'application/pdf');

        $this->postJson($this->uploadUrl($template), ['file' => $pdf])
            ->assertUnprocessable();
    }

    public function test_upload_returns_404_for_template_from_another_project(): void
    {
        $other   = Project::factory()->create(['created_by' => $this->person->id]);
        $foreign = DocumentTemplate::create([
            'project_id' => $other->id,
            'name'       => 'Foreign',
            'settings'   => [],
            'created_by' => $this->person->id,
        ]);

        $this->postJson($this->uploadUrl($foreign), ['file' => $this->fakeDocx()])
            ->assertNotFound();
    }

    public function test_upload_forbidden_for_observer(): void
    {
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);
        $template = $this->makeTemplate();

        $this->actingAs($observer)
            ->postJson($this->uploadUrl($template), ['file' => $this->fakeDocx()])
            ->assertForbidden();
    }

    // =========================================================================
    // Additional gap coverage
    // =========================================================================

    public function test_store_with_category_and_type(): void
    {
        $this->postJson($this->storeUrl(), [
            'name'     => 'Highlight Report',
            'category' => 'reporting',
            'type'     => 'highlight_report',
        ])
            ->assertCreated()
            ->assertJsonPath('data.category', 'reporting')
            ->assertJsonPath('data.type', 'highlight_report');
    }

    public function test_store_rejects_invalid_parent_id(): void
    {
        $this->postJson($this->storeUrl(), ['name' => 'Child', 'parent_id' => 99999])
            ->assertUnprocessable();
    }

    public function test_store_rejects_duplicate_scope(): void
    {
        $this->makeTemplate(['category' => 'reporting', 'type' => 'highlight_report']);

        $this->postJson($this->storeUrl(), [
            'name'     => 'Duplicate',
            'category' => 'reporting',
            'type'     => 'highlight_report',
        ])->assertUnprocessable();
    }

    public function test_update_rejects_duplicate_scope(): void
    {
        $this->makeTemplate(['category' => 'reporting', 'type' => null]);
        $target = $this->makeTemplate(['category' => 'planning', 'type' => null]);

        $this->putJson($this->updateUrl($target), ['category' => 'reporting'])
            ->assertUnprocessable();
    }

    public function test_index_does_not_leak_other_projects_templates(): void
    {
        $other        = Project::factory()->create(['created_by' => $this->person->id]);
        $foreignTempl = DocumentTemplate::create([
            'project_id' => $other->id,
            'name'       => 'Foreign Template',
            'settings'   => [],
            'created_by' => $this->person->id,
        ]);

        $this->makeTemplate(['name' => 'My Template']);

        $data  = $this->getJson($this->indexUrl())->assertOk()->json('data');
        $names = collect($data)->pluck('name');

        $this->assertTrue($names->contains('My Template'));
        $this->assertFalse($names->contains('Foreign Template'));
    }

    public function test_upload_requires_file(): void
    {
        $template = $this->makeTemplate();

        $this->postJson($this->uploadUrl($template), [])
            ->assertUnprocessable();
    }
}
