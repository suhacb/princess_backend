<?php

namespace App\Http\Controllers;

use App\Http\Requests\Template\CreateTemplateRequest;
use App\Http\Requests\Template\UpdateTemplateRequest;
use App\Http\Requests\Template\UploadTemplateRequest;
use App\Http\Resources\DocumentTemplateResource;
use App\Models\DocumentTemplate;
use App\Models\Project;
use App\Services\Document\GarageStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Document Templates
 */
class DocumentTemplateController extends Controller
{
    /**
     * Return project templates plus global templates as a nested tree.
     * Each root node carries a `children` array with its subtemplates.
     * Optional filters narrow the result set before the tree is built.
     *
     * @queryParam category string Filter to templates with this category value. Example: reporting
     * @queryParam type string Filter to templates with this type value. Example: highlight_report
     *
     * @response {"data": [{"id": 1, "name": "Base", "category": null, "type": null, "parent_id": null, "has_file": false, "settings": {}, "children": [{"id": 2, "name": "Reports", "category": "reporting", "type": null, "parent_id": 1, "has_file": false, "settings": {}, "children": []}], "created_at": "2026-06-29T00:00:00.000000Z", "updated_at": "2026-06-29T00:00:00.000000Z"}]}
     */
    public function index(Project $project, Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [DocumentTemplate::class, $project]);

        $query = DocumentTemplate::where(function ($q) use ($project) {
            $q->where('project_id', $project->id)->orWhereNull('project_id');
        });

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $templates = $query->get();

        // Build nested tree using in-memory relationships.
        $templates->each(function (DocumentTemplate $template) use ($templates) {
            $template->setRelation(
                'children',
                $templates->filter(fn (DocumentTemplate $t) => $t->parent_id === $template->id)->values()
            );
        });

        $roots = $templates->filter(fn (DocumentTemplate $t) => $t->parent_id === null)->values();

        return DocumentTemplateResource::collection($roots);
    }

    /**
     * Create a new template node for a project.
     *
     * @bodyParam name string required The display name of the template. Example: Highlight Report Template
     * @bodyParam category string The document category this template applies to; null = applies to all categories. Example: reporting
     * @bodyParam type string The document type this template applies to; null = applies to all types in the category. Example: highlight_report
     * @bodyParam parent_id integer ID of the parent template in the tree; null for a root node. Example: 1
     * @bodyParam settings object Key/value settings map (fonts, colors, header, footer, margins, logo_s3_key, etc.). Example: {"font": "Arial", "margin": 20}
     *
     * @response 201 {"data": {"id": 2, "project_id": 1, "parent_id": null, "name": "Reports", "category": "reporting", "type": null, "has_file": false, "settings": {}, "children": [], "created_at": "2026-06-29T00:00:00.000000Z", "updated_at": "2026-06-29T00:00:00.000000Z"}}
     * @response 422 {"message": "The name field is required.", "errors": {"name": ["The name field is required."]}}
     * @response 422 {"message": "A template with this project, category, and type combination already exists.", "errors": {"category": ["A template with this project, category, and type combination already exists."]}}
     */
    public function store(CreateTemplateRequest $request, Project $project): DocumentTemplateResource
    {
        $this->authorize('create', [DocumentTemplate::class, $project]);

        $template = DocumentTemplate::create(array_merge($request->validated(), [
            'project_id' => $project->id,
            'created_by' => auth()->user()->person_id,
        ]));

        return new DocumentTemplateResource($template->load('createdBy'));
    }

    /**
     * Update a template's name, category, type, parent, or settings.
     * All fields are optional; only supplied fields are changed.
     *
     * @bodyParam name string The display name of the template. Example: Highlight Report Template
     * @bodyParam category string The document category this template applies to; send null to clear. Example: reporting
     * @bodyParam type string The document type this template applies to; send null to clear. Example: highlight_report
     * @bodyParam parent_id integer ID of the new parent template; send null to make this a root node. Example: 1
     * @bodyParam settings object Replaces the stored settings map entirely. Example: {"font": "Arial", "margin": 20}
     *
     * @response 200 {"data": {"id": 1, "project_id": 1, "parent_id": null, "name": "Updated Name", "category": "reporting", "type": null, "has_file": false, "settings": {"font": "Arial"}, "children": [], "created_at": "2026-06-29T00:00:00.000000Z", "updated_at": "2026-06-29T00:00:00.000000Z"}}
     * @response 404 {"message": "Not Found"}
     * @response 422 {"message": "A template with this project, category, and type combination already exists.", "errors": {"category": ["A template with this project, category, and type combination already exists."]}}
     */
    public function update(
        UpdateTemplateRequest $request,
        Project $project,
        DocumentTemplate $template,
    ): DocumentTemplateResource {
        $this->authorize('update', [DocumentTemplate::class, $project, $template]);

        $template->update($request->validated());

        return new DocumentTemplateResource($template->fresh()->load('createdBy'));
    }

    /**
     * Soft-delete a template.
     * Without ?force the template is soft-deleted and its children are left in place.
     * With ?force=true the template and all descendants are permanently deleted.
     *
     * @queryParam force boolean When true, permanently deletes the template and all its children recursively. Example: true
     *
     * @response 204 {}
     * @response 404 {"message": "Not Found"}
     */
    public function destroy(Request $request, Project $project, DocumentTemplate $template): JsonResponse
    {
        $this->authorize('delete', [DocumentTemplate::class, $project, $template]);

        if ($request->boolean('force')) {
            $this->deleteRecursive($template);
        } else {
            $template->delete();
        }

        return response()->json(null, 204);
    }

    /**
     * Upload a .docx file for a template.
     * The file is stored in the project bucket and the template's s3_key is updated.
     * Only .docx files are accepted; maximum size is controlled by DOCUMENT_UPLOAD_MAX_MB.
     *
     * @bodyParam file file required The .docx template file to upload.
     *
     * @response 200 {"data": {"id": 1, "project_id": 1, "parent_id": null, "name": "Base", "category": null, "type": null, "has_file": true, "settings": {}, "children": [], "created_at": "2026-06-29T00:00:00.000000Z", "updated_at": "2026-06-29T00:00:00.000000Z"}}
     * @response 403 {"message": "Forbidden"}
     * @response 404 {"message": "Not Found"}
     * @response 422 {"message": "The file field is required.", "errors": {"file": ["The file field is required."]}}
     */
    public function upload(
        UploadTemplateRequest $request,
        Project $project,
        DocumentTemplate $template,
        GarageStorageService $storage,
    ): DocumentTemplateResource {
        $this->authorize('upload', [DocumentTemplate::class, $project, $template]);

        $file = $request->file('file');
        $key  = "templates/{$template->id}/original.docx";

        $storage->put($project, $key, fopen($file->getRealPath(), 'r'));

        $template->update(['s3_key' => $key]);

        return new DocumentTemplateResource($template->fresh()->load('createdBy'));
    }

    private function deleteRecursive(DocumentTemplate $template): void
    {
        foreach ($template->children()->withTrashed()->get() as $child) {
            $this->deleteRecursive($child);
        }

        $template->forceDelete();
    }
}
