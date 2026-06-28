<?php

namespace App\Services\Document;

use App\Contracts\DocumentEditorDriver;
use App\Models\Person;
use App\Models\QaDocument;

/**
 * M365 (SharePoint + OnlyOffice for Business) editor driver stub.
 *
 * Data compatibility contract for future implementers:
 *   - `openSession()` should return a SharePoint co-authoring URL and
 *     session token rather than an OnlyOffice JWT config.
 *   - `handleCallback()` is unused for M365 (co-authoring is managed
 *     server-side by SharePoint); implement as a no-op or for webhooks.
 *   - `document_versions.onlyoffice_key` is unused for M365 sessions.
 *   - `document_versions.converted_md_key` is always S3-based regardless
 *     of provider (conversion runs server-side against a local copy).
 */
class M365EditorService implements DocumentEditorDriver
{
    public function openSession(QaDocument $document, Person $user): array
    {
        throw new \RuntimeException('M365 editor driver not yet implemented.');
    }

    public function handleCallback(string $versionKey, array $payload): void
    {
        throw new \RuntimeException('M365 editor driver not yet implemented.');
    }
}
