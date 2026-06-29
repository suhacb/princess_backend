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
     * List all templates for a project as a flat list (includes global templates).
     * Filterable by category and/or type.
     *
     * @response {"data": [{"id": 1, "name": "Base", "category": null, "type": null, "parent_id": null}]}
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
     * @response 201 {"data": {"id": 2, "name": "Reports", "category": "reporting"}}
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
     *
     * @response {"data": {"id": 1, "settings": {"font": "Arial"}}}
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
     * Soft-delete a template. Pass ?force=true to cascade to children.
     *
     * @response 204 {}
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
     * Upload a .docx file for a template; stores it in the project bucket.
     *
     * @response 200 {"data": {"id": 1, "has_file": true}}
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
