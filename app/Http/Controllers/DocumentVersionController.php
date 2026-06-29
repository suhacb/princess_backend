<?php

namespace App\Http\Controllers;

use App\Contracts\DocumentStorageDriver;
use App\Http\Requests\QaDocument\UploadDocumentRequest;
use App\Http\Resources\DocumentVersionResource;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\QaDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @tags Document Versions
 */
class DocumentVersionController extends Controller
{
    /**
     * List paginated version history for a document (oldest first).
     *
     * @response {"data": [{"id": 1, "version_number": 1, "file_name": "Plan v1.docx"}], "meta": {}, "links": {}}
     */
    public function index(Project $project, QaDocument $qaDocument): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [DocumentVersion::class, $project]);

        abort_if($qaDocument->project_id !== $project->id, 404);

        $versions = $qaDocument->versions()->with('createdBy')->paginate(25);

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

    /**
     * Upload a new file version for a document.
     *
     * @response 201 {"data": {"id": 3, "version_number": 3, "file_name": "plan.docx"}}
     * @response 403 {"message": "Forbidden"}
     * @response 422 {"message": "The file field is required."}
     */
    public function upload(
        Project $project,
        QaDocument $qaDocument,
        UploadDocumentRequest $request,
        DocumentStorageDriver $storage,
    ): DocumentVersionResource {
        $this->authorize('upload', [DocumentVersion::class, $project, $qaDocument]);

        $file = $request->file('file');
        $uuid = (string) Str::uuid();
        $key  = "documents/{$qaDocument->id}/versions/{$uuid}/original.{$file->extension()}";

        $newVersion = DB::transaction(function () use ($project, $qaDocument, $request, $storage, $file, $key) {
            $storage->put($project, $key, fopen($file->getRealPath(), 'r'));

            $nextNumber = ($qaDocument->versions()->max('version_number') ?? 0) + 1;

            $newVersion = DocumentVersion::create([
                'document_id'     => $qaDocument->id,
                'version_number'  => $nextNumber,
                's3_key'          => $key,
                'file_name'       => $file->getClientOriginalName(),
                'file_size_bytes' => $file->getSize(),
                'comment'         => $request->input('comment'),
                'created_by'      => auth()->user()->person_id,
            ]);

            $qaDocument->update(['current_version_id' => $newVersion->id]);

            return $newVersion;
        });

        return new DocumentVersionResource($newVersion->load('createdBy'));
    }

    /**
     * Generate a short-lived presigned URL for downloading a document file.
     * Redirects the client directly to Garage S3.
     *
     * @response 302 {}
     * @response 404 {"message": "Not Found"}
     */
    public function download(
        Project $project,
        QaDocument $qaDocument,
        Request $request,
        DocumentStorageDriver $storage,
    ): RedirectResponse {
        $this->authorize('download', [DocumentVersion::class, $project, $qaDocument]);

        $versionId = $request->query('version');

        if ($versionId !== null) {
            $version = DocumentVersion::where('document_id', $qaDocument->id)
                ->findOrFail($versionId);
        } else {
            $version = $qaDocument->currentVersion;
            abort_if($version === null, 404);
        }

        $url = $storage->temporaryUrl($project, $version->s3_key, now()->addMinutes(5));

        return redirect($url, 302);
    }
}
