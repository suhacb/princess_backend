<?php

namespace App\Http\Controllers;

use App\Enums\HighlightReportStatus;
use App\Http\Requests\HighlightReport\HighlightReportRequest;
use App\Http\Resources\HighlightReportResource;
use App\Models\HighlightReport;
use App\Models\Project;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @tags Highlight Reports
 */
class HighlightReportController extends Controller
{
    /**
     * List highlight reports for a project.
     *
     * @queryParam status string Filter by status (draft, submitted, approved). Example: approved
     * @queryParam stage_id integer Filter by stage ID. Example: 2
     *
     * @response {"data": [{"id": 1, "ref": "HLR-001", "status": "draft"}]}
     */
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

    /**
     * Create a highlight report.
     *
     * @response 201 {"data": {"id": 1, "ref": "HLR-001", "status": "draft"}}
     */
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

    /**
     * Get a highlight report.
     *
     * @response {"data": {"id": 1, "ref": "HLR-001"}}
     */
    public function show(Project $project, HighlightReport $highlightReport): HighlightReportResource
    {
        $this->authorize('view', [HighlightReport::class, $project, $highlightReport]);

        return new HighlightReportResource($highlightReport->load(['submittedBy', 'approvedBy']));
    }

    /**
     * Update a highlight report (draft only).
     *
     * @response {"data": {"id": 1, "title": "Updated"}}
     * @response 409 {"message": "Only draft reports can be edited."}
     */
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

    /**
     * Delete a highlight report (draft only, soft delete).
     *
     * @response 204 {}
     */
    public function destroy(Project $project, HighlightReport $highlightReport): Response
    {
        $this->authorize('delete', [HighlightReport::class, $project, $highlightReport]);

        $highlightReport->delete();

        return response()->noContent();
    }

    /**
     * Submit a draft highlight report to the project board.
     *
     * @response {"data": {"id": 1, "status": "submitted"}}
     * @response 409 {"message": "Only draft reports can be submitted."}
     */
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

    /**
     * Board approves a submitted highlight report.
     *
     * @response {"data": {"id": 1, "status": "approved"}}
     * @response 409 {"message": "Only submitted reports can be approved."}
     */
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
