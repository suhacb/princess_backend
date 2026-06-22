<?php

namespace App\Http\Controllers;

use App\Enums\HighlightReportStatus;
use App\Http\Requests\HighlightReport\HighlightReportRequest;
use App\Http\Resources\HighlightReportResource;
use App\Models\HighlightReport;
use App\Models\Project;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class HighlightReportController extends Controller
{
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [HighlightReport::class, $project]);

        $query = $project->highlightReports()->with(['submittedBy', 'approvedBy']);

        if (request()->filled('status')) {
            $query->where('status', request('status'));
        }

        if (request()->filled('stage_id')) {
            $query->where('stage_id', request('stage_id'));
        }

        return HighlightReportResource::collection($query->latest()->get());
    }

    public function store(HighlightReportRequest $request, Project $project): HighlightReportResource
    {
        $this->authorize('create', [HighlightReport::class, $project]);

        $report = $project->highlightReports()->create(array_merge(
            $request->validated(),
            [
                'ref'        => HighlightReport::nextRef($project->id),
                'status'     => HighlightReportStatus::Draft->value,
                'created_by' => auth()->user()->person_id,
            ]
        ));

        return new HighlightReportResource($report->load(['submittedBy', 'approvedBy']));
    }

    public function show(Project $project, HighlightReport $highlightReport): HighlightReportResource
    {
        $this->authorize('view', [HighlightReport::class, $project, $highlightReport]);

        return new HighlightReportResource($highlightReport->load(['submittedBy', 'approvedBy']));
    }

    public function update(HighlightReportRequest $request, Project $project, HighlightReport $highlightReport): HighlightReportResource
    {
        $this->authorize('update', [HighlightReport::class, $project, $highlightReport]);

        if ($highlightReport->status !== HighlightReportStatus::Draft) {
            abort(409, 'Only draft reports can be edited.');
        }

        $highlightReport->update(array_merge(
            $request->validated(),
            ['updated_by' => auth()->user()->person_id]
        ));

        return new HighlightReportResource($highlightReport->load(['submittedBy', 'approvedBy']));
    }

    public function destroy(Project $project, HighlightReport $highlightReport): Response
    {
        $this->authorize('delete', [HighlightReport::class, $project, $highlightReport]);

        $highlightReport->delete();

        return response()->noContent();
    }

    public function submit(Project $project, HighlightReport $highlightReport): HighlightReportResource
    {
        $this->authorize('submit', [HighlightReport::class, $project, $highlightReport]);

        if ($highlightReport->status !== HighlightReportStatus::Draft) {
            abort(409, 'Only draft reports can be submitted.');
        }

        $highlightReport->update([
            'status'       => HighlightReportStatus::Submitted->value,
            'submitted_by' => auth()->user()->person_id,
            'submitted_at' => now(),
            'updated_by'   => auth()->user()->person_id,
        ]);

        return new HighlightReportResource($highlightReport->load(['submittedBy', 'approvedBy']));
    }

    public function approve(Project $project, HighlightReport $highlightReport): HighlightReportResource
    {
        $this->authorize('approve', [HighlightReport::class, $project, $highlightReport]);

        if ($highlightReport->status !== HighlightReportStatus::Submitted) {
            abort(409, 'Only submitted reports can be approved.');
        }

        $highlightReport->update([
            'status'      => HighlightReportStatus::Approved->value,
            'approved_by' => auth()->user()->person_id,
            'approved_at' => now(),
            'updated_by'  => auth()->user()->person_id,
        ]);

        return new HighlightReportResource($highlightReport->load(['submittedBy', 'approvedBy']));
    }
}
