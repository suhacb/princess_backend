<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductDescription\ProjectProductDescriptionRequest;
use App\Http\Resources\ProjectProductDescriptionResource;
use App\Models\Project;
use App\Models\ProjectProductDescription;
use Illuminate\Http\Response;

/**
 * @tags Product Description
 */
class ProjectProductDescriptionController extends Controller
{
    /**
     * Get the Project Product Description.
     *
     * @response {"data": {"id": 1, "title": "Banking CORE System"}}
     */
    public function show(Project $project): ProjectProductDescriptionResource
    {
        $this->authorize('view', [ProjectProductDescription::class, $project]);

        $ppd = $project->productDescription()->firstOrFail();

        return new ProjectProductDescriptionResource($ppd);
    }

    /**
     * Create the Project Product Description.
     *
     * @response 201 {"data": {"id": 1, "title": "Banking CORE System"}}
     */
    public function store(ProjectProductDescriptionRequest $request, Project $project): ProjectProductDescriptionResource
    {
        $this->authorize('create', [ProjectProductDescription::class, $project]);

        $ppd = $project->productDescription()->create(array_merge(
            $request->validated(),
            ['created_by' => auth()->user()->person_id]
        ));

        return new ProjectProductDescriptionResource($ppd);
    }

    /**
     * Update the Project Product Description.
     *
     * @response {"data": {"id": 1, "title": "Updated title"}}
     */
    public function update(ProjectProductDescriptionRequest $request, Project $project): ProjectProductDescriptionResource
    {
        $ppd = $project->productDescription()->firstOrFail();

        $this->authorize('update', [ProjectProductDescription::class, $project, $ppd]);

        $ppd->update(array_merge(
            $request->validated(),
            ['updated_by' => auth()->user()->person_id]
        ));

        return new ProjectProductDescriptionResource($ppd);
    }

    /**
     * Delete the Project Product Description.
     *
     * @response 204 {}
     */
    public function destroy(Project $project): Response
    {
        $ppd = $project->productDescription()->firstOrFail();

        $this->authorize('delete', [ProjectProductDescription::class, $project, $ppd]);

        $ppd->delete();

        return response()->noContent();
    }
}
