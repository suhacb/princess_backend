<?php

namespace App\Http\Controllers;

use App\Http\Requests\QualityRegister\QualityRegisterRequest;
use App\Http\Resources\QualityRegisterEntryResource;
use App\Models\Project;
use App\Models\QualityRegisterEntry;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @tags Quality Register
 */
class QualityRegisterController extends Controller
{
    /**
     * List quality register entries for a project.
     *
     * @response {"data": [{"id": 1, "product_name": "...", "quality_method": "review"}]}
     */
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [QualityRegisterEntry::class, $project]);

        $entries = $project->qualityRegisterEntries()->with('signedOffBy')->latest()->get();

        return QualityRegisterEntryResource::collection($entries);
    }

    /**
     * Create a quality register entry.
     *
     * @response 201 {"data": {"id": 1, "product_name": "..."}}
     */
    public function store(QualityRegisterRequest $request, Project $project): QualityRegisterEntryResource
    {
        $this->authorize('create', [QualityRegisterEntry::class, $project]);

        $entry = $project->qualityRegisterEntries()->create($request->validated());

        return new QualityRegisterEntryResource($entry->load('signedOffBy'));
    }

    /**
     * Get a quality register entry.
     *
     * @response {"data": {"id": 1, "product_name": "..."}}
     */
    public function show(Project $project, QualityRegisterEntry $qualityRegisterEntry): QualityRegisterEntryResource
    {
        $this->authorize('view', [QualityRegisterEntry::class, $project, $qualityRegisterEntry]);

        return new QualityRegisterEntryResource($qualityRegisterEntry->load('signedOffBy'));
    }

    /**
     * Update a quality register entry.
     *
     * @response {"data": {"id": 1, "product_name": "Updated"}}
     */
    public function update(QualityRegisterRequest $request, Project $project, QualityRegisterEntry $qualityRegisterEntry): QualityRegisterEntryResource
    {
        $this->authorize('update', [QualityRegisterEntry::class, $project, $qualityRegisterEntry]);

        $qualityRegisterEntry->update($request->validated());

        return new QualityRegisterEntryResource($qualityRegisterEntry->load('signedOffBy'));
    }

    /**
     * Delete a quality register entry.
     *
     * @response 204 {}
     */
    public function destroy(Project $project, QualityRegisterEntry $qualityRegisterEntry): Response
    {
        $this->authorize('delete', [QualityRegisterEntry::class, $project, $qualityRegisterEntry]);

        $qualityRegisterEntry->delete();

        return response()->noContent();
    }
}
