<?php

namespace App\Http\Controllers;

use App\Documents\DocumentLinkValidator;
use App\Http\Resources\QaDocumentResource;
use App\Models\CheckpointReport;
use App\Models\ExceptionReport;
use App\Models\HighlightReport;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\QaDocument;
use App\Models\Stage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * @tags QA Documents
 */
class DocumentLinkController extends Controller
{
    private const ENTITY_TYPES = [
        'meeting'           => Meeting::class,
        'highlight_report'  => HighlightReport::class,
        'checkpoint_report' => CheckpointReport::class,
        'exception_report'  => ExceptionReport::class,
        'stage'             => Stage::class,
        'project'           => Project::class,
    ];

    /**
     * Link a QA document to a project entity.
     *
     * @bodyParam documentable_type string required Entity type alias (meeting, highlight_report, checkpoint_report, exception_report, stage, project). Example: meeting
     * @bodyParam documentable_id integer required Entity ID. Example: 1
     *
     * @response {"data": {"id": 1, "type": "meeting_minutes", "documentable": {"type": "Meeting", "id": 5}}}
     * @response 422 {"message": "Document type 'highlight_report' cannot link to a Meeting."}
     * @response 422 {"message": "The entity does not exist in this project."}
     */
    public function link(Request $request, Project $project, QaDocument $qaDocument): QaDocumentResource
    {
        $this->authorize('update', [QaDocument::class, $project, $qaDocument]);

        $validated = $request->validate([
            'documentable_type' => ['required', 'string', Rule::in(array_keys(self::ENTITY_TYPES))],
            'documentable_id'   => ['required', 'integer'],
        ]);

        $entityClass = self::ENTITY_TYPES[$validated['documentable_type']];
        $entityId    = (int) $validated['documentable_id'];

        abort_unless(
            DocumentLinkValidator::isCompatible($entityClass, $qaDocument->type),
            422,
            "Document type '{$qaDocument->type->value}' cannot link to a " . class_basename($entityClass) . '.'
        );

        abort_if(
            $this->resolveEntityInProject($entityClass, $entityId, $project) === null,
            422,
            'The entity does not exist in this project.'
        );

        // Clear any other document currently linked to the same entity to keep one-to-one.
        QaDocument::where('documentable_type', $entityClass)
            ->where('documentable_id', $entityId)
            ->where('id', '!=', $qaDocument->id)
            ->update(['documentable_type' => null, 'documentable_id' => null]);

        $qaDocument->update([
            'documentable_type' => $entityClass,
            'documentable_id'   => $entityId,
        ]);

        return new QaDocumentResource($qaDocument->fresh()->load('documentable'));
    }

    /**
     * Remove the link between a QA document and its entity.
     *
     * @response 204 {}
     */
    public function unlink(Project $project, QaDocument $qaDocument): Response
    {
        $this->authorize('update', [QaDocument::class, $project, $qaDocument]);

        $qaDocument->update([
            'documentable_type' => null,
            'documentable_id'   => null,
        ]);

        return response()->noContent();
    }

    private function resolveEntityInProject(string $entityClass, int $entityId, Project $project): ?object
    {
        if ($entityClass === Project::class) {
            return $project->id === $entityId ? $project : null;
        }

        return $entityClass::where('id', $entityId)
            ->where('project_id', $project->id)
            ->first();
    }
}
