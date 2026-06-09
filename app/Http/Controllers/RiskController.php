<?php

namespace App\Http\Controllers;

use App\Enums\RiskStatus;
use App\Http\Requests\RiskLog\RiskRequest;
use App\Http\Resources\RiskResource;
use App\Models\Project;
use App\Models\Risk;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class RiskController extends Controller
{
    /**
     * List risks for a project.
     *
     * @response {"data": [{"id": 1, "title": "...", "risk_score": 12}]}
     */
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Risk::class, $project]);

        $risks = $project->risks()->with('owner')->latest()->get();

        return RiskResource::collection($risks);
    }

    /**
     * Create a risk.
     *
     * @response 201 {"data": {"id": 1, "title": "...", "status": "open"}}
     */
    public function store(RiskRequest $request, Project $project): RiskResource
    {
        $this->authorize('create', [Risk::class, $project]);

        $risk = $project->risks()->create(array_merge(
            $request->validated(),
            [
                'raised_at' => now(),
                'status'    => RiskStatus::Open->value,
            ]
        ));

        return new RiskResource($risk->load('owner'));
    }

    /**
     * Get a risk.
     *
     * @response {"data": {"id": 1, "title": "..."}}
     */
    public function show(Project $project, Risk $risk): RiskResource
    {
        $this->authorize('view', [Risk::class, $project, $risk]);

        return new RiskResource($risk->load('owner'));
    }

    /**
     * Update a risk.
     *
     * @response {"data": {"id": 1, "title": "Updated"}}
     */
    public function update(RiskRequest $request, Project $project, Risk $risk): RiskResource
    {
        $this->authorize('update', [Risk::class, $project, $risk]);

        $risk->update($request->validated());

        return new RiskResource($risk->load('owner'));
    }

    /**
     * Delete a risk.
     *
     * @response 204 {}
     */
    public function destroy(Project $project, Risk $risk): Response
    {
        $this->authorize('delete', [Risk::class, $project, $risk]);

        $risk->delete();

        return response()->noContent();
    }
}
