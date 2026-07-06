<?php

namespace App\Http\Controllers;

use App\Enums\PlanStatus;
use App\Enums\PlanType;
use App\Http\Requests\Plan\StorePlanRequest;
use App\Http\Requests\Plan\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @tags Plans
 */
class PlanController extends Controller
{
    /**
     * List plans for a project.
     *
     * @queryParam type string Filter by plan type (stage, team, exception). Example: stage
     *
     * @response {"data": [{"id": 1, "type": "stage", "name": "Initiation Stage Plan"}]}
     */
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Plan::class, $project]);

        $query = $project->plans()->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return PlanResource::collection($query->get());
    }

    /**
     * Create a plan.
     *
     * @response 201 {"data": {"id": 1, "type": "stage", "status": "draft"}}
     */
    public function store(StorePlanRequest $request, Project $project): PlanResource
    {
        $this->authorize('create', [Plan::class, $project]);

        $validated = $request->validated();

        if (isset($validated['replaces_plan_id'])) {
            abort_if(
                ! $project->plans()->where('id', $validated['replaces_plan_id'])->exists(),
                422,
                'The replaced plan must belong to this project.'
            );
        }

        $plan = $project->plans()->create(array_merge(
            $validated,
            [
                'status'     => PlanStatus::Draft->value,
                'created_by' => auth()->user()->person_id,
            ]
        ));

        return new PlanResource($plan);
    }

    /**
     * Get a plan.
     *
     * @response {"data": {"id": 1, "name": "..."}}
     */
    public function show(Project $project, Plan $plan): PlanResource
    {
        $this->authorize('view', [Plan::class, $project, $plan]);

        return new PlanResource($plan->load(['stage', 'replaces', 'approvedBy']));
    }

    /**
     * Update a plan.
     *
     * @response {"data": {"id": 1, "name": "Updated"}}
     */
    public function update(UpdatePlanRequest $request, Project $project, Plan $plan): PlanResource
    {
        $this->authorize('update', [Plan::class, $project, $plan]);

        $plan->update(array_merge(
            $request->validated(),
            ['updated_by' => auth()->user()->person_id]
        ));

        return new PlanResource($plan->load(['stage', 'replaces', 'approvedBy']));
    }

    /**
     * Delete a plan.
     *
     * @response 204 {}
     */
    public function destroy(Project $project, Plan $plan): Response
    {
        $this->authorize('delete', [Plan::class, $project, $plan]);

        $plan->delete();

        return response()->noContent();
    }

    /**
     * Approve a plan (Board roles only).
     *
     * @response {"data": {"id": 1, "status": "approved"}}
     */
    public function approve(Project $project, Plan $plan): PlanResource
    {
        $this->authorize('approve', [Plan::class, $project, $plan]);

        $plan->update([
            'status'      => PlanStatus::Approved->value,
            'approved_by' => auth()->user()->person_id,
            'approved_at' => now(),
            'updated_by'  => auth()->user()->person_id,
        ]);

        return new PlanResource($plan->load(['stage', 'replaces', 'approvedBy']));
    }
}
