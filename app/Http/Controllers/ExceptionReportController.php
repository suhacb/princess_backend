<?php

namespace App\Http\Controllers;

use App\Enums\ExceptionReportStatus;
use App\Enums\ExceptionTriggerType;
use App\Http\Requests\ExceptionReport\ExceptionReportRequest;
use App\Http\Resources\ExceptionReportResource;
use App\Models\ExceptionReport;
use App\Models\Project;
use App\Models\WorkPackage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @tags Exception Reports
 */
class ExceptionReportController extends Controller
{
    /**
     * List exception reports for a project.
     *
     * @queryParam status string Filter by status (draft, submitted, closed). Example: submitted
     * @queryParam stage_id integer Filter by stage ID. Example: 2
     * @queryParam trigger_type string Filter by trigger type (tolerance_time, tolerance_cost, tolerance_scope, tolerance_quality, tolerance_risk, issue_escalation, manual). Example: tolerance_time
     *
     * @response {"data": [{"id": 1, "ref": "EXR-001", "status": "draft", "trigger_type": "tolerance_time"}]}
     */
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [ExceptionReport::class, $project]);

        $query = $project->exceptionReports()->with(['submittedBy', 'decidedBy']);

        if (request()->filled('status')) {
            $query->where('status', request('status'));
        }

        if (request()->filled('stage_id')) {
            $query->where('stage_id', request('stage_id'));
        }

        if (request()->filled('trigger_type')) {
            $query->where('trigger_type', request('trigger_type'));
        }

        return ExceptionReportResource::collection($query->latest()->get());
    }

    /**
     * Create an exception report.
     *
     * @response 201 {"data": {"id": 1, "ref": "EXR-001", "status": "draft"}}
     */
    public function store(ExceptionReportRequest $request, Project $project): ExceptionReportResource
    {
        $this->authorize('create', [ExceptionReport::class, $project]);

        $report = $project->exceptionReports()->create(array_merge(
            $request->validated(),
            [
                'ref'        => ExceptionReport::nextRef($project->id),
                'status'     => ExceptionReportStatus::Draft->value,
                'created_by' => auth()->user()->person_id,
            ]
        ));

        return new ExceptionReportResource($report->load(['submittedBy', 'decidedBy']));
    }

    /**
     * Get an exception report.
     *
     * @response {"data": {"id": 1, "ref": "EXR-001", "options": []}}
     */
    public function show(Project $project, ExceptionReport $exceptionReport): ExceptionReportResource
    {
        $this->authorize('view', [ExceptionReport::class, $project, $exceptionReport]);

        return new ExceptionReportResource($exceptionReport->load(['submittedBy', 'decidedBy']));
    }

    /**
     * Update an exception report (draft only).
     *
     * @response {"data": {"id": 1, "title": "Updated"}}
     * @response 409 {"message": "Only draft reports can be edited."}
     */
    public function update(ExceptionReportRequest $request, Project $project, ExceptionReport $exceptionReport): ExceptionReportResource
    {
        $this->authorize('update', [ExceptionReport::class, $project, $exceptionReport]);

        if ($exceptionReport->status !== ExceptionReportStatus::Draft) {
            abort(409, 'Only draft reports can be edited.');
        }

        $exceptionReport->update(array_merge(
            $request->validated(),
            ['updated_by' => auth()->user()->person_id]
        ));

        return new ExceptionReportResource($exceptionReport->load(['submittedBy', 'decidedBy']));
    }

    /**
     * Delete an exception report (draft only, soft delete).
     *
     * @response 204 {}
     */
    public function destroy(Project $project, ExceptionReport $exceptionReport): Response
    {
        $this->authorize('delete', [ExceptionReport::class, $project, $exceptionReport]);

        $exceptionReport->delete();

        return response()->noContent();
    }

    /**
     * Submit a draft exception report to the project board.
     *
     * @response {"data": {"id": 1, "status": "submitted"}}
     * @response 409 {"message": "Only draft reports can be submitted."}
     */
    public function submit(Project $project, ExceptionReport $exceptionReport): ExceptionReportResource
    {
        $this->authorize('submit', [ExceptionReport::class, $project, $exceptionReport]);

        if ($exceptionReport->status !== ExceptionReportStatus::Draft) {
            abort(409, 'Only draft reports can be submitted.');
        }

        $exceptionReport->update([
            'status'       => ExceptionReportStatus::Submitted->value,
            'submitted_by' => auth()->user()->person_id,
            'submitted_at' => now(),
            'updated_by'   => auth()->user()->person_id,
        ]);

        return new ExceptionReportResource($exceptionReport->load(['submittedBy', 'decidedBy']));
    }

    /**
     * Board closes a submitted exception report with a decision.
     *
     * @response {"data": {"id": 1, "status": "closed", "board_decision": "Approve exception plan."}}
     * @response 409 {"message": "Only submitted reports can be closed."}
     * @response 422 {"message": "The board decision field is required."}
     */
    public function close(Request $request, Project $project, ExceptionReport $exceptionReport): ExceptionReportResource
    {
        $this->authorize('close', [ExceptionReport::class, $project, $exceptionReport]);

        if ($exceptionReport->status !== ExceptionReportStatus::Submitted) {
            abort(409, 'Only submitted reports can be closed.');
        }

        $validated = $request->validate([
            'board_decision' => ['required', 'string'],
        ]);

        $exceptionReport->update([
            'status'         => ExceptionReportStatus::Closed->value,
            'board_decision' => $validated['board_decision'],
            'decided_by'     => auth()->user()->person_id,
            'decided_at'     => now(),
            'updated_by'     => auth()->user()->person_id,
        ]);

        return new ExceptionReportResource($exceptionReport->load(['submittedBy', 'decidedBy']));
    }

    /**
     * Automatically create a draft exception report from a work package tolerance breach.
     *
     * @response 201 {"data": {"id": 1, "ref": "EXR-001", "trigger_type": "tolerance_time", "status": "draft"}}
     */
    public function raiseException(Project $project, WorkPackage $workPackage): ExceptionReportResource
    {
        $this->authorize('raiseException', [ExceptionReport::class, $project]);

        $varianceDays = null;
        $triggerType  = ExceptionTriggerType::ToleranceTime;

        if ($workPackage->planned_end && $workPackage->actual_end) {
            $varianceDays = (int) Carbon::parse($workPackage->planned_end)
                ->diffInDays(Carbon::parse($workPackage->actual_end), false);
        }

        $description = "Work package \"{$workPackage->title}\" has exceeded its planned end date.";
        if ($varianceDays !== null) {
            $description .= " Variance: {$varianceDays} day(s) (planned end: {$workPackage->planned_end->toDateString()}, actual end: {$workPackage->actual_end->toDateString()}).";
        }

        $report = $project->exceptionReports()->create([
            'ref'            => ExceptionReport::nextRef($project->id),
            'title'          => "Exception: {$workPackage->title}",
            'trigger_type'   => $triggerType->value,
            'description'    => $description,
            'cause'          => '',
            'impact'         => '',
            'recommendation' => '',
            'status'         => ExceptionReportStatus::Draft->value,
            'created_by'     => auth()->user()->person_id,
        ]);

        return new ExceptionReportResource($report->load(['submittedBy', 'decidedBy']));
    }
}
