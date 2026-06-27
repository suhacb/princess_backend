<?php

namespace App\Http\Controllers;

use App\Models\Change;
use App\Models\Issue;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\QualityRegisterEntry;
use App\Models\Requirement;
use App\Models\Risk;
use App\Models\Stage;
use App\Models\Task;
use App\Models\WorkPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

/**
 * @tags Audit Trail
 */
class AuditTrailController extends Controller
{
    private const ENTITY_TYPES = [
        'task'                   => Task::class,
        'meeting'                => Meeting::class,
        'meeting_action_item'    => MeetingActionItem::class,
        'issue'                  => Issue::class,
        'risk'                   => Risk::class,
        'change'                 => Change::class,
        'requirement'            => Requirement::class,
        'quality_register_entry' => QualityRegisterEntry::class,
        'stage'                  => Stage::class,
        'work_package'           => WorkPackage::class,
        'project'                => Project::class,
        'project_member'         => ProjectMember::class,
    ];

    private const TITLE_FIELD = [
        Task::class                => 'title',
        Meeting::class             => 'title',
        MeetingActionItem::class   => 'description',
        Issue::class               => 'title',
        Risk::class                => 'title',
        Change::class              => 'title',
        Requirement::class         => 'title',
        QualityRegisterEntry::class => 'product_name',
        Stage::class               => 'name',
        WorkPackage::class         => 'title',
        Project::class             => 'name',
        ProjectMember::class       => 'role',
    ];

    /**
     * @response {"data": [{"id": 1, "entity_type": "task", "entity_id": 42, "entity_title": "Deploy staging", "event": "updated", "causer": {"id": 3, "name": "Ana Novak"}, "occurred_at": "2026-06-27T10:15:00Z", "changes": {"status": {"old": "todo", "new": "in_progress"}}}], "meta": {"current_page": 1, "last_page": 1, "per_page": 25, "total": 1}}
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $request->validate([
            'entity_type' => ['nullable', 'string', 'in:' . implode(',', array_keys(self::ENTITY_TYPES))],
            'actor'       => ['nullable', 'integer', 'exists:people,id'],
            'from'        => ['nullable', 'date'],
            'to'          => ['nullable', 'date'],
        ]);

        $activities = Activity::query()
            ->where('properties->project_id', $project->id)
            ->when($request->entity_type, fn ($q, $v) => $q->where('subject_type', self::ENTITY_TYPES[$v]))
            ->when($request->actor, function ($q, $v) {
                $q->whereHasMorph('causer', \App\Models\User::class, fn ($inner) => $inner->where('person_id', $v));
            })
            ->when($request->from, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->with(['causer.person', 'subject'])
            ->latest()
            ->paginate(25);

        $typeMap = array_flip(self::ENTITY_TYPES);

        $data = $activities->getCollection()->map(function (Activity $activity) use ($typeMap) {
            $causer  = $activity->causer;
            $subject = $activity->subject;
            $type    = $typeMap[$activity->subject_type] ?? $activity->subject_type;

            $titleField  = self::TITLE_FIELD[$activity->subject_type] ?? null;
            $rawTitle    = $subject && $titleField ? $subject->$titleField : null;
            $entityTitle = is_null($rawTitle)
                ? "#{$activity->subject_id}"
                : ($rawTitle instanceof \BackedEnum ? $rawTitle->value : (string) $rawTitle);

            return [
                'id'           => $activity->id,
                'entity_type'  => $type,
                'entity_id'    => $activity->subject_id,
                'entity_title' => $entityTitle,
                'event'        => $activity->event,
                'causer'       => $causer ? [
                    'id'   => $causer->person_id,
                    'name' => $causer->person?->name ?? $causer->email,
                ] : null,
                'occurred_at'  => $activity->created_at,
                'changes'      => $this->formatChanges($activity->attribute_changes?->toArray() ?? []),
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page'    => $activities->lastPage(),
                'per_page'     => $activities->perPage(),
                'total'        => $activities->total(),
            ],
        ]);
    }

    private function formatChanges(array $changes): array
    {
        $old        = $changes['old'] ?? [];
        $attributes = $changes['attributes'] ?? [];

        if (empty($old) && empty($attributes)) {
            return $changes;
        }

        $formatted = [];
        foreach ($attributes as $key => $newValue) {
            $formatted[$key] = ['old' => $old[$key] ?? null, 'new' => $newValue];
        }
        foreach ($old as $key => $oldValue) {
            if (! isset($formatted[$key])) {
                $formatted[$key] = ['old' => $oldValue, 'new' => $attributes[$key] ?? null];
            }
        }

        return $formatted;
    }
}
