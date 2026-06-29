<?php

namespace App\Http\Controllers;

use App\Contracts\DocumentEditorDriver;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\QaDocument;
use Illuminate\Http\JsonResponse;

/**
 * @tags OnlyOffice
 */
class OnlyOfficeEditorConfigController extends Controller
{
    /**
     * Open an OnlyOffice editing session and return the JWT-signed editor config.
     * The frontend passes this payload directly to the OnlyOffice JS SDK.
     *
     * @response {"document": {"key": "uuid", "url": "https://..."}, "token": "JWT"}
     * @response 403 {"message": "Forbidden"}
     */
    public function __invoke(
        Project $project,
        QaDocument $qaDocument,
        DocumentEditorDriver $editor,
    ): JsonResponse {
        $this->authorize('openEditorSession', [DocumentVersion::class, $project, $qaDocument]);

        $person = auth()->user()->person;
        $config = $editor->openSession($qaDocument, $person);

        return response()->json($config);
    }
}
