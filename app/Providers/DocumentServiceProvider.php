<?php

namespace App\Providers;

use App\Clients\OnlyOfficeClient;
use App\Contracts\DocumentEditorDriver;
use App\Contracts\DocumentStorageDriver;
use App\Enums\DocumentProvider;
use App\Services\Document\GarageStorageService;
use App\Services\Document\M365EditorService;
use App\Services\Document\M365StorageService;
use App\Services\Document\OnlyOfficeEditorService;
use Illuminate\Support\ServiceProvider;

class DocumentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OnlyOfficeClient::class, fn () => new OnlyOfficeClient(
            jwtSecret: config('princess.onlyoffice.jwt_secret', ''),
            serverUrl: config('princess.onlyoffice.url'),
            publicUrl: config('princess.onlyoffice.public_url'),
        ));

        $this->app->bind(DocumentStorageDriver::class, function ($app) {
            $project = $app->make('request')->route('project');

            return match ($project?->document_provider) {
                DocumentProvider::M365 => $app->make(M365StorageService::class),
                default                => $app->make(GarageStorageService::class),
            };
        });

        $this->app->bind(DocumentEditorDriver::class, function ($app) {
            $project = $app->make('request')->route('project');

            return match ($project?->document_provider) {
                DocumentProvider::M365 => $app->make(M365EditorService::class),
                default                => $app->make(OnlyOfficeEditorService::class),
            };
        });
    }
}
