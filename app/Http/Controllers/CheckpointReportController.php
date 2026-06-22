<?php

namespace App\Http\Controllers;

use App\Enums\CheckpointReportStatus;
use App\Http\Requests\CheckpointReport\CheckpointReportRequest;
use App\Http\Resources\CheckpointReportResource;
use App\Models\CheckpointReport;
use App\Models\Project;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CheckpointReportController extends Controller
{
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

    public function store(CheckpointReportRequest $request, Project $project): CheckpointReportResource
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

    public function show(Project $project, CheckpointReport $checkpointReport): CheckpointReportResource
    {
        $this->authorize('view', [CheckpointReport::class, $project, $checkpointReport]);

        return new CheckpointReportResource($checkpointReport->load(['submittedBy', 'acknowledgedBy', 'workPackage']));
    }

    public function update(CheckpointReportRequest $request, Project $project, CheckpointReport $checkpointReport): CheckpointReportResource
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

    public function destroy(Project $project, CheckpointReport $checkpointReport): Response
    {
        $this->authorize('delete', [CheckpointReport::class, $project, $checkpointReport]);

        $checkpointReport->delete();

        return response()->noContent();
    }

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
