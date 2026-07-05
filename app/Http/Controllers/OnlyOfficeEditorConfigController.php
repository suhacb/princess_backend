<?php

namespace App\Http\Controllers;

use App\Contracts\DocumentEditorDriver;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\QaDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags OnlyOffice
 */
class OnlyOfficeEditorConfigController extends Controller
{
    /**
     * Open an OnlyOffice session and return the JWT-signed editor config.
     * The frontend passes this payload directly to the OnlyOffice JS SDK.
     *
     * Pass ?version_id={id} to view a specific historical version read-only
     * (only the current version is ever editable).
     *
     * @response {"document": {"key": "uuid", "url": "https://..."}, "token": "JWT"}
     * @response 403 {"message": "Forbidden"}
     * @response 404 {"message": "Not Found"}
     */
    public function __invoke(
        Project $project,
        QaDocument $qaDocument,
        DocumentEditorDriver $editor,
        Request $request,
    ): JsonResponse {
        $this->authorize('openEditorSession', [DocumentVersion::class, $project, $qaDocument]);

        $validated = $request->validate([
            'version_id' => ['nullable', 'integer'],
        ]);

        $requestedVersion = null;

        if (isset($validated['version_id'])) {
            $requestedVersion = DocumentVersion::where('document_id', $qaDocument->id)
                ->find($validated['version_id']);

            abort_if($requestedVersion === null, 404);
        }

        $person = auth()->user()->person;
        $config = $editor->openSession($qaDocument, $person, $requestedVersion);

        return response()->json(['data' => $config]);
    }
}
