<?php

namespace App\Http\Controllers;

use App\Enums\WorkPackageStatus;
use App\Http\Requests\WorkPackage\WorkPackageRequest;
use App\Http\Resources\WorkPackageResource;
use App\Models\Project;
use App\Models\WorkPackage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @tags Work Packages
 */
class WorkPackageController extends Controller
{
    /**
     * List work packages for a project.
     *
     * @response {"data": [{"id": 1, "title": "...", "status": "draft"}]}
     */
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [WorkPackage::class, $project]);

        $wps = $project->workPackages()->with(['teamManager', 'products'])->latest()->get();

        return WorkPackageResource::collection($wps);
    }

    /**
     * Create a work package.
     *
     * @response 201 {"data": {"id": 1, "title": "...", "status": "draft"}}
     */
    public function store(WorkPackageRequest $request, Project $project): WorkPackageResource
    {
        $this->authorize('create', [WorkPackage::class, $project]);

        $validated = $request->validated();

        $this->assertBelongsToProject($project, $validated);

        $productIds = $validated['product_ids'] ?? [];
        unset($validated['product_ids']);

        $wp = $project->workPackages()->create(array_merge(
            $validated,
            [
                'status'     => WorkPackageStatus::Draft->value,
                'created_by' => auth()->user()->person_id,
            ]
        ));

        if ($productIds) {
            $wp->products()->sync($productIds);
        }

        return new WorkPackageResource($wp->load(['teamManager', 'products']));
    }

    /**
     * Get a work package.
     *
     * @response {"data": {"id": 1, "title": "..."}}
     */
    public function show(Project $project, WorkPackage $workPackage): WorkPackageResource
    {
        $this->authorize('view', [WorkPackage::class, $project, $workPackage]);

        return new WorkPackageResource($workPackage->load(['teamManager', 'products', 'plan']));
    }

    /**
     * Update a work package.
     *
     * @response {"data": {"id": 1, "title": "Updated"}}
     */
    public function update(WorkPackageRequest $request, Project $project, WorkPackage $workPackage): WorkPackageResource
    {
        $this->authorize('update', [WorkPackage::class, $project, $workPackage]);

        $validated = $request->validated();

        $this->assertBelongsToProject($project, $validated);

        $productIds = $validated['product_ids'] ?? null;
        unset($validated['product_ids']);

        $workPackage->update(array_merge(
            $validated,
            ['updated_by' => auth()->user()->person_id]
        ));

        if ($productIds !== null) {
            $workPackage->products()->sync($productIds);
        }

        return new WorkPackageResource($workPackage->load(['teamManager', 'products', 'plan']));
    }

    /**
     * Delete a work package (draft only).
     *
     * @response 204 {}
     */
    public function destroy(Project $project, WorkPackage $workPackage): Response
    {
        $this->authorize('delete', [WorkPackage::class, $project, $workPackage]);

        $workPackage->delete();

        return response()->noContent();
    }

    /**
     * Authorise a work package — PM issues it to the Team Manager.
     *
     * @response {"data": {"id": 1, "status": "authorized"}}
     * @response 409 {"message": "Work package is not in draft status."}
     */
    public function issue(Project $project, WorkPackage $workPackage): WorkPackageResource
    {
        $this->authorize('authorize', [WorkPackage::class, $project, $workPackage]);

        abort_if(
            $workPackage->status !== WorkPackageStatus::Draft,
            409,
            'Work package is not in draft status.'
        );

        $workPackage->update([
            'status'        => WorkPackageStatus::Authorized->value,
            'authorized_by' => auth()->user()->person_id,
            'authorized_at' => now(),
            'updated_by'    => auth()->user()->person_id,
        ]);

        return new WorkPackageResource($workPackage->load(['teamManager', 'products']));
    }

    /**
     * Team Manager accepts the work package and begins work.
     *
     * @response {"data": {"id": 1, "status": "in_progress"}}
     * @response 409 {"message": "Work package must be authorized before it can be accepted."}
     */
    public function accept(Project $project, WorkPackage $workPackage): WorkPackageResource
    {
        $this->authorize('accept', [WorkPackage::class, $project, $workPackage]);

        abort_if(
            $workPackage->status !== WorkPackageStatus::Authorized,
            409,
            'Work package must be authorized before it can be accepted.'
        );

        $workPackage->update([
            'status'       => WorkPackageStatus::InProgress->value,
            'actual_start' => now()->toDateString(),
            'updated_by'   => auth()->user()->person_id,
        ]);

        return new WorkPackageResource($workPackage->load(['teamManager', 'products']));
    }

    /**
     * Team Manager marks the work package as complete.
     *
     * @response {"data": {"id": 1, "status": "completed"}}
     * @response 409 {"message": "Work package must be in progress to be completed."}
     */
    public function complete(Project $project, WorkPackage $workPackage): WorkPackageResource
    {
        $this->authorize('complete', [WorkPackage::class, $project, $workPackage]);

        abort_if(
            $workPackage->status !== WorkPackageStatus::InProgress,
            409,
            'Work package must be in progress to be completed.'
        );

        $workPackage->update([
            'status'     => WorkPackageStatus::Completed->value,
            'actual_end' => now()->toDateString(),
            'updated_by' => auth()->user()->person_id,
        ]);

        return new WorkPackageResource($workPackage->load(['teamManager', 'products']));
    }

    /**
     * PM cancels an authorized or in-progress work package.
     *
     * @response {"data": {"id": 1, "status": "cancelled"}}
     * @response 409 {"message": "Work package cannot be cancelled in its current status."}
     */
    public function cancel(Project $project, WorkPackage $workPackage): WorkPackageResource
    {
        $this->authorize('cancel', [WorkPackage::class, $project, $workPackage]);

        abort_if(
            ! in_array($workPackage->status, [WorkPackageStatus::Authorized, WorkPackageStatus::InProgress]),
            409,
            'Work package cannot be cancelled in its current status.'
        );

        $workPackage->update([
            'status'     => WorkPackageStatus::Cancelled->value,
            'updated_by' => auth()->user()->person_id,
        ]);

        return new WorkPackageResource($workPackage->load(['teamManager', 'products']));
    }

    private function assertBelongsToProject(Project $project, array $validated): void
    {
        if (! empty($validated['plan_id'])) {
            abort_if(
                ! $project->plans()->where('id', $validated['plan_id'])->exists(),
                422,
                'Plan must belong to this project.'
            );
        }

        if (! empty($validated['team_manager_id'])) {
            abort_if(
                ! $project->members()->where('person_id', $validated['team_manager_id'])->exists(),
                422,
                'Team manager must be a member of this project.'
            );
        }

        if (! empty($validated['product_ids'])) {
            $count = $project->products()->whereIn('id', $validated['product_ids'])->count();
            abort_if(
                $count !== count($validated['product_ids']),
                422,
                'All products must belong to this project.'
            );
        }
    }
}
