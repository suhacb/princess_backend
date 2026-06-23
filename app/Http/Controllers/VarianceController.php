<?php

namespace App\Http\Controllers;

use App\Models\HighlightReport;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Stage;
use App\Models\WorkPackage;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

/**
 * @tags Reporting
 */
class VarianceController extends Controller
{
    /**
     * Return time variance for all plans and work packages in a project.
     *
     * @response {"data": {"stage": null, "plans": [], "work_packages": []}}
     */
    public function index(Project $project): JsonResponse
    {
        $this->authorize('viewAny', [HighlightReport::class, $project]);

        $plans = $project->plans()->get();
        $workPackages = $project->workPackages()->get();

        return response()->json([
            'data' => [
                'stage'         => null,
                'plans'         => $plans->map(fn ($p) => $this->planVariance($p))->values(),
                'work_packages' => $workPackages->map(fn ($wp) => $this->wpVariance($wp))->values(),
            ],
        ]);
    }

    /**
     * Return time variance for plans and work packages scoped to a specific stage.
     *
     * @response {"data": {"stage": {"id": 1, "name": "Delivery"}, "plans": [], "work_packages": []}}
     */
    public function show(Project $project, Stage $stage): JsonResponse
    {
        $this->authorize('viewAny', [HighlightReport::class, $project]);

        $plans = $project->plans()->where('stage_id', $stage->id)->get();
        $planIds = $plans->pluck('id');
        $workPackages = $project->workPackages()->whereIn('plan_id', $planIds)->get();

        return response()->json([
            'data' => [
                'stage' => [
                    'id'   => $stage->id,
                    'name' => $stage->name,
                ],
                'plans'         => $plans->map(fn ($p) => $this->planVariance($p))->values(),
                'work_packages' => $workPackages->map(fn ($wp) => $this->wpVariance($wp))->values(),
            ],
        ]);
    }

    private function planVariance(Plan $plan): array
    {
        $varianceDays = $this->computeVarianceDays($plan->planned_end, $plan->actual_end);
        $toleranceDays = $this->parseTolerance($plan->tolerance_time);

        return [
            'id'                 => $plan->id,
            'name'               => $plan->name,
            'type'               => $plan->type,
            'planned_start'      => $plan->planned_start?->toDateString(),
            'planned_end'        => $plan->planned_end?->toDateString(),
            'actual_start'       => $plan->actual_start?->toDateString(),
            'actual_end'         => $plan->actual_end?->toDateString(),
            'time_variance_days' => $varianceDays,
            'tolerance_time'     => $plan->tolerance_time,
            'tolerance_breached' => $this->isTolBreached($varianceDays, $toleranceDays),
        ];
    }

    private function wpVariance(WorkPackage $wp): array
    {
        $varianceDays = $this->computeVarianceDays($wp->planned_end, $wp->actual_end);
        $toleranceDays = $this->parseTolerance($wp->tolerance_time);

        return [
            'id'                 => $wp->id,
            'title'              => $wp->title,
            'planned_start'      => $wp->planned_start?->toDateString(),
            'planned_end'        => $wp->planned_end?->toDateString(),
            'actual_start'       => $wp->actual_start?->toDateString(),
            'actual_end'         => $wp->actual_end?->toDateString(),
            'time_variance_days' => $varianceDays,
            'tolerance_time'     => $wp->tolerance_time,
            'tolerance_breached' => $this->isTolBreached($varianceDays, $toleranceDays),
        ];
    }

    private function computeVarianceDays(mixed $plannedEnd, mixed $actualEnd): ?int
    {
        if ($plannedEnd === null || $actualEnd === null) {
            return null;
        }

        return (int) Carbon::parse($plannedEnd)->diffInDays(Carbon::parse($actualEnd), false);
    }

    private function parseTolerance(?string $tolerance): ?int
    {
        if (empty($tolerance)) {
            return null;
        }

        if (preg_match('/^(\d+)\s*d$/i', $tolerance, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/^(\d+)\s*w$/i', $tolerance, $m)) {
            return (int) $m[1] * 7;
        }

        if (preg_match('/^(\d+)\s*m$/i', $tolerance, $m)) {
            return (int) $m[1] * 30;
        }

        if (is_numeric($tolerance)) {
            return (int) $tolerance;
        }

        return null;
    }

    private function isTolBreached(?int $varianceDays, ?int $toleranceDays): bool
    {
        if ($varianceDays === null || $toleranceDays === null) {
            return false;
        }

        return $varianceDays > $toleranceDays;
    }
}
