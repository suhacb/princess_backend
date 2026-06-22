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

class ExceptionReportController extends Controller
{
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

    public function show(Project $project, ExceptionReport $exceptionReport): ExceptionReportResource
    {
        $this->authorize('view', [ExceptionReport::class, $project, $exceptionReport]);

        return new ExceptionReportResource($exceptionReport->load(['submittedBy', 'decidedBy']));
    }

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

    public function destroy(Project $project, ExceptionReport $exceptionReport): Response
    {
        $this->authorize('delete', [ExceptionReport::class, $project, $exceptionReport]);

        $exceptionReport->delete();

        return response()->noContent();
    }

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
