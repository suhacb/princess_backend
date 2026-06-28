<?php

namespace App\Contracts;

use App\Models\Person;
use App\Models\QaDocument;

interface DocumentEditorDriver
{
    /**
     * Open an editing session for the given document and user.
     *
     * Returns a driver-specific config payload for the frontend editor.
     * For OnlyOffice this is a JWT-signed config object.
     * For M365 this is a SharePoint co-authoring URL.
     *
     * A new document_versions row (with a fresh onlyoffice_key) must be
     * created by the implementation before returning the config.
     */
    public function openSession(QaDocument $document, Person $user): array;

    /**
     * Handle an incoming callback from the editor service.
     *
     * $versionKey is the onlyoffice_key (or equivalent) that identifies
     * which editing session the callback belongs to.
     * $payload is the raw decoded callback body from the editor service.
     */
    public function handleCallback(string $versionKey, array $payload): void;
}
