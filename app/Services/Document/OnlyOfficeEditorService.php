<?php

namespace App\Services\Document;

use App\Clients\OnlyOfficeClient;
use App\Contracts\DocumentEditorDriver;
use App\Models\DocumentVersion;
use App\Models\Person;
use App\Models\QaDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OnlyOfficeEditorService implements DocumentEditorDriver
{
    public function __construct(
        private readonly OnlyOfficeClient $client,
        private readonly GarageStorageService $storage,
    ) {}

    /**
     * Open an OnlyOffice editing session for $document as $user.
     *
     * Creates a new document_versions row with a fresh onlyoffice_key UUID,
     * generates a presigned S3 download URL for the current file (so OnlyOffice
     * can fetch it), and returns a JWT-signed editor config for the frontend.
     */
    public function openSession(QaDocument $document, Person $user): array
    {
        $currentVersion = $document->currentVersion;
        $project        = $document->project;

        $uuid      = (string) Str::uuid();
        $extension = $currentVersion ? strtolower(pathinfo($currentVersion->file_name, PATHINFO_EXTENSION) ?: 'docx') : 'docx';
        $s3Key     = "documents/{$document->id}/versions/{$uuid}/original.{$extension}";

        $version = DocumentVersion::create([
            'document_id'     => $document->id,
            'version_number'  => ($document->versions()->max('version_number') ?? 0) + 1,
            's3_key'          => $s3Key,
            'file_name'       => $currentVersion?->file_name ?? "document.{$extension}",
            'file_size_bytes' => $currentVersion?->file_size_bytes ?? 0,
            'onlyoffice_key'  => $uuid,
            'created_by'      => $user->id,
        ]);

        // Use the internal Garage endpoint so OnlyOffice (inside Docker) can fetch the file.
        $fileUrl = $currentVersion
            ? $this->storage->internalTemporaryUrl($project, $currentVersion->s3_key, now()->addMinutes(5))
            : null;

        // Use the internal app base URL when configured so OnlyOffice (inside Docker) can POST callbacks.
        $callbackBase = rtrim(config('princess.onlyoffice.callback_base_url', config('app.url')), '/');
        $callbackUrl  = $callbackBase . '/api/onlyoffice/callback/' . $uuid;

        return $this->client->generateEditorConfig($document, $version, $user, $callbackUrl, $fileUrl);
    }

    /**
     * Dispatch an OnlyOffice callback by status code.
     *
     * Status 1  — editing in progress  → update last_active_at
     * Status 2/6 — saved / force-saved → download from payload URL, push to S3, set current_version_id
     * Status 4  — closed without save  → mark closed_without_changes
     */
    public function handleCallback(string $versionKey, array $payload): void
    {
        $version = DocumentVersion::where('onlyoffice_key', $versionKey)->firstOrFail();
        $status  = (int) ($payload['status'] ?? 0);

        match ($status) {
            1       => $this->markActive($version),
            2, 6    => $this->saveFile($version, $payload['url']),
            4       => $this->markClosed($version),
            default => null,
        };
    }

    private function markActive(DocumentVersion $version): void
    {
        DB::table('document_versions')
            ->where('id', $version->id)
            ->update(['last_active_at' => now()]);
    }

    private function saveFile(DocumentVersion $version, string $url): void
    {
        $contents = Http::get($url)->body();
        $project  = $version->document->project;

        DB::transaction(function () use ($version, $project, $contents) {
            $this->storage->put($project, $version->s3_key, $contents);
            $version->document->update(['current_version_id' => $version->id]);
        });
    }

    private function markClosed(DocumentVersion $version): void
    {
        DB::table('document_versions')
            ->where('id', $version->id)
            ->update(['closed_without_changes' => true]);
    }
}
