<?php

namespace App\Http\Controllers;

use App\Enums\ChangeStatus;
use App\Enums\IssueStatus;
use App\Enums\QaDocumentStatus;
use App\Enums\RiskStatus;
use App\Enums\TestSessionStatus;
use App\Enums\WorkPackageStatus;
use App\Models\CheckpointReport;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Reporting
 */
class PeriodSummaryController extends Controller
{
    /**
     * Aggregate a period summary for a project (work packages, issues, risks, changes, quality, lessons).
     *
     * @queryParam from string required Start date of the period (YYYY-MM-DD). Example: 2026-06-01
     * @queryParam to string required End date of the period (YYYY-MM-DD). Example: 2026-06-30
     * @queryParam stage_id integer Scope the summary to a specific stage. Example: 2
     *
     * @response {"data": {"period": {"from": "2026-06-01", "to": "2026-06-30"}, "work_packages": {}, "issues": {}, "risks": {}, "changes": {}, "quality": {}, "lessons": {}}}
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('viewAny', [CheckpointReport::class, $project]);

        $validated = $request->validate([
            'from'     => ['required', 'date'],
            'to'       => ['required', 'date', 'after_or_equal:from'],
            'stage_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('stages', 'id')],
        ]);

        $from    = $validated['from'];
        $to      = $validated['to'];
        $stageId = $validated['stage_id'] ?? null;

        $wpQuery = fn () => $project->workPackages()
            ->when($stageId, fn ($q) => $q->whereHas('plan', fn ($p) => $p->where('stage_id', $stageId)));

        $issueQuery = fn () => $project->issues()
            ->when($stageId, fn ($q) => $q->where('stage_id', $stageId));

        $riskQuery = fn () => $project->risks()
            ->when($stageId, fn ($q) => $q->where('stage_id', $stageId));

        $changeQuery = fn () => $project->changes();

        $today = now()->toDateString();

        $data = [
            'period' => [
                'from'     => $from,
                'to'       => $to,
                'stage_id' => $stageId,
            ],
            'work_packages' => [
                'authorized'  => $wpQuery()->where('status', WorkPackageStatus::Authorized->value)
                    ->whereBetween('authorized_at', [$from, $to . ' 23:59:59'])->count(),
                'completed'   => $wpQuery()->where('status', WorkPackageStatus::Completed->value)
                    ->whereBetween('actual_end', [$from, $to])->count(),
                'in_progress' => $wpQuery()->where('status', WorkPackageStatus::InProgress->value)->count(),
                'overdue'     => $wpQuery()->whereNotIn('status', [
                    WorkPackageStatus::Completed->value,
                    WorkPackageStatus::Cancelled->value,
                ])->where('planned_end', '<', $today)->count(),
            ],
            'issues' => [
                'raised'     => $issueQuery()->whereBetween('raised_at', [$from, $to . ' 23:59:59'])->count(),
                'closed'     => $issueQuery()->where('status', IssueStatus::Closed->value)
                    ->whereBetween('resolved_at', [$from, $to . ' 23:59:59'])->count(),
                'escalated'  => $issueQuery()->where('status', IssueStatus::Escalated->value)
                    ->whereBetween('escalated_at', [$from, $to . ' 23:59:59'])->count(),
                'open_count' => $issueQuery()->whereNotIn('status', [
                    IssueStatus::Closed->value,
                ])->count(),
            ],
            'risks' => [
                'new'        => $riskQuery()->whereBetween('created_at', [$from, $to . ' 23:59:59'])->count(),
                'updated'    => $riskQuery()->whereBetween('updated_at', [$from, $to . ' 23:59:59'])
                    ->whereColumn('created_at', '!=', 'updated_at')->count(),
                'open_count' => $riskQuery()->where('status', RiskStatus::Open->value)->count(),
            ],
            'changes' => [
                'submitted' => $changeQuery()->whereBetween('raised_at', [$from, $to . ' 23:59:59'])->count(),
                'approved'  => $changeQuery()->where('status', ChangeStatus::Approved->value)
                    ->whereBetween('decision_at', [$from, $to . ' 23:59:59'])->count(),
                'rejected'  => $changeQuery()->where('status', ChangeStatus::Rejected->value)
                    ->whereBetween('decision_at', [$from, $to . ' 23:59:59'])->count(),
            ],
            'quality' => [
                'qa_documents_confirmed'      => $project->qaDocuments()
                    ->where('status', QaDocumentStatus::Confirmed->value)
                    ->whereBetween('confirmed_at', [$from, $to . ' 23:59:59'])->count(),
                'test_sessions_completed'     => $project->testSessions()
                    ->where('status', TestSessionStatus::Completed->value)
                    ->whereBetween('updated_at', [$from, $to . ' 23:59:59'])->count(),
                'acceptance_criteria_accepted' => $project->acceptanceCriteria()
                    ->whereNotNull('accepted_at')
                    ->whereBetween('accepted_at', [$from, $to . ' 23:59:59'])->count(),
            ],
            'lessons' => [
                'added' => $project->lessons()
                    ->whereBetween('created_at', [$from, $to . ' 23:59:59'])->count(),
            ],
            'daily_log_entries' => [
                'count' => $project->dailyLogEntries()
                    ->whereBetween('created_at', [$from, $to . ' 23:59:59'])->count(),
            ],
        ];

        return response()->json(['data' => $data]);
    }
}
