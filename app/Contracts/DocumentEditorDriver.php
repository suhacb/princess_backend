<?php

namespace App\Contracts;

use App\Models\DocumentVersion;
use App\Models\Person;
use App\Models\QaDocument;

interface DocumentEditorDriver
{
    /**
     * Open a session for the given document and user.
     *
     * Returns a driver-specific config payload for the frontend editor.
     * For OnlyOffice this is a JWT-signed config object.
     * For M365 this is a SharePoint co-authoring URL.
     *
     * $requestedVersion, when given, opens that specific version instead of
     * the document's current version. Only the current version is ever
     * editable — any other version must be opened read-only (the user has
     * to revert to make an old version current before editing it), so the
     * implementation must force read-only mode whenever $requestedVersion is
     * not the document's current version.
     *
     * A new document_versions row (with a fresh onlyoffice_key) must be
     * created by the implementation before returning the config, unless the
     * session is read-only (nothing to save, so no placeholder version).
     */
    public function openSession(QaDocument $document, Person $user, ?DocumentVersion $requestedVersion = null): array;

    /**
     * Handle an incoming callback from the editor service.
     *
     * $versionKey is the onlyoffice_key (or equivalent) that identifies
     * which editing session the callback belongs to.
     * $payload is the raw decoded callback body from the editor service.
     */
    public function handleCallback(string $versionKey, array $payload): void;
}
