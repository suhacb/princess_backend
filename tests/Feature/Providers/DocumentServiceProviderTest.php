<?php

namespace Tests\Feature\Providers;

use App\Contracts\DocumentEditorDriver;
use App\Contracts\DocumentStorageDriver;
use App\Enums\DocumentProvider;
use App\Models\Person;
use App\Models\Project;
use App\Models\QaDocument;
use App\Services\Document\GarageStorageService;
use App\Services\Document\M365EditorService;
use App\Services\Document\M365StorageService;
use App\Services\Document\OnlyOfficeEditorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

class DocumentServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->app->forgetInstance('request');
        parent::tearDown();
    }

    private function bindProjectToRoute(Project $project): void
    {
        $request = Request::create('/fake');
        $route   = new Route('GET', '/fake', fn () => null);
        $route->bind($request);
        $route->setParameter('project', $project);
        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);
    }

    // -------------------------------------------------------------------------
    // Default (no project in route) — falls back to Garage / OnlyOffice
    // -------------------------------------------------------------------------

    public function test_storage_driver_defaults_to_garage_when_no_project_in_route(): void
    {
        $this->assertInstanceOf(GarageStorageService::class, app(DocumentStorageDriver::class));
    }

    public function test_editor_driver_defaults_to_onlyoffice_when_no_project_in_route(): void
    {
        $this->assertInstanceOf(OnlyOfficeEditorService::class, app(DocumentEditorDriver::class));
    }

    // -------------------------------------------------------------------------
    // Garage project
    // -------------------------------------------------------------------------

    public function test_storage_driver_resolves_to_garage_for_garage_project(): void
    {
        $project = Project::factory()->create(['document_provider' => DocumentProvider::Garage]);
        $this->bindProjectToRoute($project);

        $this->assertInstanceOf(GarageStorageService::class, app(DocumentStorageDriver::class));
    }

    public function test_editor_driver_resolves_to_onlyoffice_for_garage_project(): void
    {
        $project = Project::factory()->create(['document_provider' => DocumentProvider::Garage]);
        $this->bindProjectToRoute($project);

        $this->assertInstanceOf(OnlyOfficeEditorService::class, app(DocumentEditorDriver::class));
    }

    // -------------------------------------------------------------------------
    // M365 project
    // -------------------------------------------------------------------------

    public function test_storage_driver_resolves_to_m365_for_m365_project(): void
    {
        $project = Project::factory()->create(['document_provider' => DocumentProvider::M365]);
        $this->bindProjectToRoute($project);

        $this->assertInstanceOf(M365StorageService::class, app(DocumentStorageDriver::class));
    }

    public function test_editor_driver_resolves_to_m365_for_m365_project(): void
    {
        $project = Project::factory()->create(['document_provider' => DocumentProvider::M365]);
        $this->bindProjectToRoute($project);

        $this->assertInstanceOf(M365EditorService::class, app(DocumentEditorDriver::class));
    }

    // -------------------------------------------------------------------------
    // M365 stubs throw on use
    // -------------------------------------------------------------------------

    public function test_m365_storage_throws_when_called(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('M365 storage driver not yet implemented.');

        $project = Project::factory()->create(['document_provider' => DocumentProvider::M365]);
        $this->bindProjectToRoute($project);

        app(DocumentStorageDriver::class)->put($project, 'key', 'content');
    }

    public function test_m365_editor_throws_when_called(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('M365 editor driver not yet implemented.');

        $project = Project::factory()->create(['document_provider' => DocumentProvider::M365]);
        $this->bindProjectToRoute($project);

        $person   = Person::factory()->create();
        $document = QaDocument::factory()->create([
            'project_id' => $project->id,
            'created_by' => $person->id,
        ]);

        app(DocumentEditorDriver::class)->openSession($document, $person);
    }
}
