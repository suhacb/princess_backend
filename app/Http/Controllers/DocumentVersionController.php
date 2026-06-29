<?php

namespace App\Http\Controllers;

use App\Contracts\DocumentStorageDriver;
use App\Http\Resources\DocumentVersionResource;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\QaDocument;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * @tags Document Versions
 */
class DocumentVersionController extends Controller
{
    /**
     * List version history for a document (oldest first).
     *
     * @response {"data": [{"id": 1, "version_number": 1, "file_name": "Plan v1.docx"}]}
     */
    public function index(Project $project, QaDocument $qaDocument): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [DocumentVersion::class, $project]);

        abort_if($qaDocument->project_id !== $project->id, 404);

        $versions = $qaDocument->versions()->with('createdBy')->get();

        return DocumentVersionResource::collection($versions);
    }

    /**
     * Revert to a previous version by copying its S3 object as a new version.
     *
     * @response 201 {"data": {"id": 2, "version_number": 2, "comment": "Reverted to v1"}}
     * @response 404 {"message": "Not Found"}
     */
    public function revert(
        Project $project,
        QaDocument $qaDocument,
        DocumentVersion $version,
        DocumentStorageDriver $storage,
    ): DocumentVersionResource {
        $this->authorize('revert', [DocumentVersion::class, $project, $qaDocument]);

        abort_if($qaDocument->project_id !== $project->id, 404);
        abort_if($version->document_id !== $qaDocument->id, 404);

        $newVersion = DB::transaction(function () use ($project, $qaDocument, $version, $storage) {
            $nextNumber = $qaDocument->versions()->max('version_number') + 1;
            $newKey = "documents/{$qaDocument->id}/versions/{$nextNumber}/{$version->file_name}";

            $storage->copy($project, $version->s3_key, $newKey);

            $newVersion = DocumentVersion::create([
                'document_id'    => $qaDocument->id,
                'version_number' => $nextNumber,
                's3_key'         => $newKey,
                'file_name'      => $version->file_name,
                'file_size_bytes' => $version->file_size_bytes,
                'comment'        => "Reverted to v{$version->version_number}",
                'created_by'     => auth()->user()->person_id,
            ]);

            $qaDocument->update(['current_version_id' => $newVersion->id]);

            return $newVersion;
        });

        return new DocumentVersionResource($newVersion->load('createdBy'));
    }
}
