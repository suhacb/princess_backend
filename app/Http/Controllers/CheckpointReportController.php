<?php

namespace App\Http\Controllers;

use App\Enums\CheckpointReportStatus;
use App\Http\Requests\CheckpointReport\StoreCheckpointReportRequest;
use App\Http\Requests\CheckpointReport\UpdateCheckpointReportRequest;
use App\Http\Resources\CheckpointReportResource;
use App\Models\CheckpointReport;
use App\Models\Project;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @tags Checkpoint Reports
 */
class CheckpointReportController extends Controller
{
    /**
     * List checkpoint reports for a project.
     *
     * @queryParam status string Filter by status (draft, submitted, acknowledged). Example: submitted
     * @queryParam work_package_id integer Filter by work package ID. Example: 3
     *
     * @response {"data": [{"id": 1, "ref": "CPR-001", "status": "draft"}]}
     */
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [CheckpointReport::class, $project]);

        $query = $project->checkpointReports()->with(['submittedBy', 'acknowledgedBy', 'workPackage']);

        if (request()->filled('status')) {
            $query->where('status', request('status'));
        }

        if (request()->filled('work_package_id')) {
            $query->where('work_package_id', request('work_package_id'));
        }

        return CheckpointReportResource::collection($query->latest()->get());
    }

    /**
     * Create a checkpoint report.
     *
     * @response 201 {"data": {"id": 1, "ref": "CPR-001", "status": "draft"}}
     */
    public function store(StoreCheckpointReportRequest $request, Project $project): CheckpointReportResource
    {
        $this->authorize('create', [CheckpointReport::class, $project]);

        $report = $project->checkpointReports()->create(array_merge(
            $request->validated(),
            [
                'ref'        => CheckpointReport::nextRef($project->id),
                'status'     => CheckpointReportStatus::Draft->value,
                'created_by' => auth()->user()->person_id,
            ]
        ));

        return new CheckpointReportResource($report->load(['submittedBy', 'acknowledgedBy', 'workPackage']));
    }

    /**
     * Get a checkpoint report.
     *
     * @response {"data": {"id": 1, "ref": "CPR-001"}}
     */
    public function show(Project $project, CheckpointReport $checkpointReport): CheckpointReportResource
    {
        $this->authorize('view', [CheckpointReport::class, $project, $checkpointReport]);

        return new CheckpointReportResource($checkpointReport->load(['document', 'submittedBy', 'acknowledgedBy', 'workPackage']));
    }

    /**
     * Update a checkpoint report (draft only).
     *
     * @response {"data": {"id": 1, "title": "Updated"}}
     * @response 409 {"message": "Only draft reports can be edited."}
     */
    public function update(UpdateCheckpointReportRequest $request, Project $project, CheckpointReport $checkpointReport): CheckpointReportResource
    {
        $this->authorize('update', [CheckpointReport::class, $project, $checkpointReport]);

        if ($checkpointReport->status !== CheckpointReportStatus::Draft) {
            abort(409, 'Only draft reports can be edited.');
        }

        $checkpointReport->update(array_merge(
            $request->validated(),
            ['updated_by' => auth()->user()->person_id]
        ));

        return new CheckpointReportResource($checkpointReport->load(['submittedBy', 'acknowledgedBy', 'workPackage']));
    }

    /**
     * Delete a checkpoint report (draft only, soft delete).
     *
     * @response 204 {}
     */
    public function destroy(Project $project, CheckpointReport $checkpointReport): Response
    {
        $this->authorize('delete', [CheckpointReport::class, $project, $checkpointReport]);

        $checkpointReport->delete();

        return response()->noContent();
    }

    /**
     * Submit a draft checkpoint report to the PM.
     *
     * @response {"data": {"id": 1, "status": "submitted"}}
     * @response 409 {"message": "Only draft reports can be submitted."}
     */
    public function submit(Project $project, CheckpointReport $checkpointReport): CheckpointReportResource
    {
        $this->authorize('submit', [CheckpointReport::class, $project, $checkpointReport]);

        if ($checkpointReport->status !== CheckpointReportStatus::Draft) {
            abort(409, 'Only draft reports can be submitted.');
        }

        $checkpointReport->update([
            'status'       => CheckpointReportStatus::Submitted->value,
            'submitted_by' => auth()->user()->person_id,
            'submitted_at' => now(),
            'updated_by'   => auth()->user()->person_id,
        ]);

        return new CheckpointReportResource($checkpointReport->load(['submittedBy', 'acknowledgedBy', 'workPackage']));
    }

    /**
     * PM acknowledges a submitted checkpoint report.
     *
     * @response {"data": {"id": 1, "status": "acknowledged"}}
     * @response 409 {"message": "Only submitted reports can be acknowledged."}
     */
    public function acknowledge(Project $project, CheckpointReport $checkpointReport): CheckpointReportResource
    {
        $this->authorize('acknowledge', [CheckpointReport::class, $project, $checkpointReport]);

        if ($checkpointReport->status !== CheckpointReportStatus::Submitted) {
            abort(409, 'Only submitted reports can be acknowledged.');
        }

        $checkpointReport->update([
            'status'          => CheckpointReportStatus::Acknowledged->value,
            'acknowledged_by' => auth()->user()->person_id,
            'acknowledged_at' => now(),
            'updated_by'      => auth()->user()->person_id,
        ]);

        return new CheckpointReportResource($checkpointReport->load(['submittedBy', 'acknowledgedBy', 'workPackage']));
    }
}
