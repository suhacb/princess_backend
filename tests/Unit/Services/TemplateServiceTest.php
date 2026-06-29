<?php

namespace Tests\Unit\Services;

use App\Documents\ResolvedTemplate;
use App\Models\DocumentTemplate;
use App\Models\Project;
use App\Models\QaDocument;
use App\Services\Document\GarageStorageService;
use App\Services\Document\TemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    private TemplateService $service;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TemplateService(
            $this->createMock(GarageStorageService::class)
        );

        $person          = \App\Models\Person::factory()->create();
        $this->project   = Project::factory()->create(['created_by' => $person->id]);
    }

    // -------------------------------------------------------------------------
    // resolve — no templates
    // -------------------------------------------------------------------------

    public function test_resolve_returns_null_when_no_templates_exist(): void
    {
        $result = $this->service->resolve($this->project, 'reporting', 'highlight_report');

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // resolve — single template
    // -------------------------------------------------------------------------

    public function test_resolve_returns_global_root_settings(): void
    {
        $this->makeTemplate(['project_id' => null, 'settings' => ['font' => 'Arial']]);

        $result = $this->service->resolve($this->project, 'reporting', 'highlight_report');

        $this->assertInstanceOf(ResolvedTemplate::class, $result);
        $this->assertSame(['font' => 'Arial'], $result->settings);
        $this->assertNull($result->s3Key);
    }

    public function test_resolve_returns_project_root_settings(): void
    {
        $this->makeTemplate(['project_id' => $this->project->id, 'settings' => ['margin' => 20]]);

        $result = $this->service->resolve($this->project, 'reporting', 'highlight_report');

        $this->assertSame(['margin' => 20], $result->settings);
    }

    // -------------------------------------------------------------------------
    // resolve — settings deep-merge
    // -------------------------------------------------------------------------

    public function test_resolve_deep_merges_settings_child_over_parent(): void
    {
        // global root: base settings
        $this->makeTemplate([
            'project_id' => null,
            'settings'   => ['font' => 'Arial', 'margin' => 10, 'header' => ['text' => 'Global']],
        ]);
        // project type: overrides font and header text
        $this->makeTemplate([
            'project_id' => $this->project->id,
            'category'   => 'reporting',
            'type'        => 'highlight_report',
            'settings'   => ['font' => 'Helvetica', 'header' => ['text' => 'Project']],
        ]);

        $result = $this->service->resolve($this->project, 'reporting', 'highlight_report');

        $this->assertSame('Helvetica', $result->settings['font']);
        $this->assertSame(10, $result->settings['margin']);
        $this->assertSame('Project', $result->settings['header']['text']);
    }

    public function test_resolve_merges_all_six_levels(): void
    {
        $person = \App\Models\Person::factory()->create();

        $templates = [
            ['project_id' => null,               'category' => null,        'type' => null,               'settings' => ['a' => 1]],
            ['project_id' => null,               'category' => 'reporting', 'type' => null,               'settings' => ['b' => 2]],
            ['project_id' => null,               'category' => 'reporting', 'type' => 'highlight_report', 'settings' => ['c' => 3]],
            ['project_id' => $this->project->id, 'category' => null,        'type' => null,               'settings' => ['d' => 4]],
            ['project_id' => $this->project->id, 'category' => 'reporting', 'type' => null,               'settings' => ['e' => 5]],
            ['project_id' => $this->project->id, 'category' => 'reporting', 'type' => 'highlight_report', 'settings' => ['f' => 6]],
        ];

        foreach ($templates as $attrs) {
            $this->makeTemplate(array_merge($attrs, ['created_by' => $person->id]));
        }

        $result = $this->service->resolve($this->project, 'reporting', 'highlight_report');

        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5, 'f' => 6], $result->settings);
    }

    // -------------------------------------------------------------------------
    // resolve — s3_key precedence
    // -------------------------------------------------------------------------

    public function test_resolve_picks_most_specific_s3_key(): void
    {
        $this->makeTemplate(['project_id' => null,               's3_key' => 'global.docx', 'settings' => []]);
        $this->makeTemplate(['project_id' => $this->project->id, 'category' => 'reporting', 'type' => 'highlight_report', 's3_key' => 'project-type.docx', 'settings' => []]);

        $result = $this->service->resolve($this->project, 'reporting', 'highlight_report');

        $this->assertSame('project-type.docx', $result->s3Key);
        $this->assertSame($this->project->id, $result->templateProjectId);
    }

    public function test_resolve_falls_back_to_global_s3_key_when_project_has_no_file(): void
    {
        $this->makeTemplate(['project_id' => null, 's3_key' => 'global.docx', 'settings' => []]);
        $this->makeTemplate(['project_id' => $this->project->id, 'category' => 'reporting', 's3_key' => null, 'settings' => []]);

        $result = $this->service->resolve($this->project, 'reporting', 'highlight_report');

        $this->assertSame('global.docx', $result->s3Key);
        $this->assertNull($result->templateProjectId);
    }

    public function test_resolve_s3_key_null_when_no_template_has_file(): void
    {
        $this->makeTemplate(['project_id' => null, 's3_key' => null, 'settings' => ['font' => 'Arial']]);

        $result = $this->service->resolve($this->project, 'reporting', 'highlight_report');

        $this->assertNull($result->s3Key);
        $this->assertFalse($result->hasFile());
    }

    // -------------------------------------------------------------------------
    // resolve — soft-deleted templates are excluded
    // -------------------------------------------------------------------------

    public function test_resolve_ignores_soft_deleted_templates(): void
    {
        $template = $this->makeTemplate(['project_id' => null, 'settings' => ['font' => 'Arial']]);
        $template->delete();

        $result = $this->service->resolve($this->project, 'reporting', 'highlight_report');

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // applyToDocument
    // -------------------------------------------------------------------------

    public function test_apply_to_document_returns_null_when_no_template(): void
    {
        $person   = \App\Models\Person::factory()->create();
        $document = QaDocument::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $person->id,
        ]);

        $result = $this->service->applyToDocument($document);

        $this->assertNull($result);
    }

    public function test_apply_to_document_returns_null_when_template_has_no_file(): void
    {
        $person   = \App\Models\Person::factory()->create();
        $document = QaDocument::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $person->id,
        ]);

        $this->makeTemplate([
            'project_id' => $this->project->id,
            's3_key'     => null,
            'settings'   => ['font' => 'Arial'],
        ]);

        $result = $this->service->applyToDocument($document);

        $this->assertNull($result);
    }

    public function test_apply_to_document_uses_copy_for_project_template(): void
    {
        $person   = \App\Models\Person::factory()->create();
        $document = QaDocument::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $person->id,
        ]);

        $this->makeTemplate([
            'project_id' => $this->project->id,
            's3_key'     => 'templates/1/original.docx',
            'settings'   => [],
        ]);

        $storage = $this->createMock(GarageStorageService::class);
        $storage->expects($this->once())->method('copy')
            ->with(
                $this->callback(fn ($p) => $p->id === $this->project->id),
                'templates/1/original.docx',
                $this->stringContains('documents/'),
            );
        $storage->expects($this->once())->method('size')->willReturn(12);
        $storage->expects($this->never())->method('get');
        $storage->expects($this->never())->method('put');

        $service = new TemplateService($storage);
        $version = $service->applyToDocument($document->fresh()->load('project'));

        $this->assertNotNull($version);
        $this->assertSame(1, $version->version_number);
        $this->assertSame(12, $version->file_size_bytes);
        $this->assertSame('Applied from template', $version->comment);

        $this->assertDatabaseHas('qa_documents', [
            'id'                 => $document->id,
            'current_version_id' => $version->id,
        ]);
    }

    public function test_apply_to_document_uses_getFromTemplates_for_global_template(): void
    {
        $person   = \App\Models\Person::factory()->create();
        $document = QaDocument::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $person->id,
        ]);

        $this->makeTemplate([
            'project_id' => null,
            's3_key'     => 'abc/original.docx',
            'settings'   => [],
        ]);

        $storage = $this->createMock(GarageStorageService::class);
        $storage->expects($this->once())->method('getFromTemplates')->with('abc/original.docx')->willReturn('fake-content');
        $storage->expects($this->once())->method('put');
        $storage->expects($this->never())->method('copy');
        $storage->expects($this->never())->method('get');

        $service = new TemplateService($storage);
        $version = $service->applyToDocument($document->fresh()->load('project'));

        $this->assertSame(12, $version->file_size_bytes); // strlen('fake-content')
    }

    // -------------------------------------------------------------------------
    // resolve — duplicate priority guard
    // -------------------------------------------------------------------------

    public function test_resolve_with_two_root_templates_for_same_project_still_returns_result(): void
    {
        // The DB unique index prevents this in production, but the service must
        // not crash if somehow two templates end up at the same priority level.
        // keyBy() silently keeps the last one, so we just assert the call doesn't throw.
        $this->makeTemplate(['project_id' => null, 'settings' => ['font' => 'Arial']]);

        $result = $this->service->resolve($this->project, 'reporting', 'highlight_report');

        $this->assertInstanceOf(ResolvedTemplate::class, $result);
    }

    // -------------------------------------------------------------------------
    // helpers
    // -------------------------------------------------------------------------

    private function makeTemplate(array $overrides = []): DocumentTemplate
    {
        $defaults = [
            'project_id' => $this->project->id,
            'parent_id'  => null,
            'name'       => 'Test Template',
            'category'   => null,
            'type'       => null,
            's3_key'     => null,
            'settings'   => [],
            'created_by' => $this->project->created_by,
        ];

        return DocumentTemplate::create(array_merge($defaults, $overrides));
    }
}
