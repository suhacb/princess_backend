<?php

namespace App\Http\Controllers;

use App\Enums\AcceptanceCriterionDecision;
use App\Enums\AcceptanceCriterionStatus;
use App\Enums\RequirementType;
use App\Http\Requests\AcceptanceCriterion\StoreAcceptanceCriterionRequest;
use App\Http\Requests\AcceptanceCriterion\UpdateAcceptanceCriterionRequest;
use App\Http\Resources\AcceptanceCriterionResource;
use App\Models\AcceptanceCriterion;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * @tags Acceptance Criteria
 */
class AcceptanceCriterionController extends Controller
{
    /**
     * List acceptance criteria for a project.
     *
     * @queryParam requirement_id integer Filter by requirement ID. Example: 3
     * @queryParam status string Filter by status (draft, approved). Example: approved
     * @queryParam supplier_passed boolean Filter by supplier pass result. Example: true
     * @queryParam client_passed boolean Filter by client pass result. Example: true
     *
     * @response {"data": [{"id": 1, "ref": "AC-001", "status": "draft"}]}
     */
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [AcceptanceCriterion::class, $project]);

        $query = $project->acceptanceCriteria()->with(['requirement', 'verifier'])->latest();

        if ($request->filled('requirement_id')) {
            $query->where('requirement_id', $request->requirement_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->boolean('supplier_passed', null) !== null) {
            $query->where('supplier_passed', $request->boolean('supplier_passed'));
        }
        if ($request->boolean('client_passed', null) !== null) {
            $query->where('client_passed', $request->boolean('client_passed'));
        }

        return AcceptanceCriterionResource::collection($query->get());
    }

    /**
     * Create an acceptance criterion.
     *
     * @response 201 {"data": {"id": 1, "ref": "AC-001", "status": "draft"}}
     */
    public function store(StoreAcceptanceCriterionRequest $request, Project $project): AcceptanceCriterionResource
    {
        $this->authorize('create', [AcceptanceCriterion::class, $project]);

        $validated = $request->validated();

        $this->assertRequirementBelongsToProject($project, $validated['requirement_id']);

        $validated['ref']               = AcceptanceCriterion::nextRef($project->id);
        $validated['status']            = AcceptanceCriterionStatus::Draft->value;
        $validated['version']           = 1;
        $validated['supplier_passed']   = false;
        $validated['client_passed']     = false;
        $validated['supplier_decision'] = AcceptanceCriterionDecision::Pending->value;
        $validated['client_decision']   = AcceptanceCriterionDecision::Pending->value;
        $validated['project_id']        = $project->id;
        $validated['created_by']        = auth()->user()->person_id;

        $criterion = DB::transaction(function () use ($validated) {
            $criterion = AcceptanceCriterion::create($validated);

            $criterion->snapshotVersion($criterion->created_by);

            return $criterion;
        });

        return new AcceptanceCriterionResource($criterion->load(['requirement', 'verifier']));
    }

    /**
     * Get an acceptance criterion.
     *
     * @response {"data": {"id": 1, "ref": "AC-001", "description": "..."}}
     */
    public function show(Project $project, AcceptanceCriterion $acceptanceCriterion): AcceptanceCriterionResource
    {
        $this->authorize('view', [AcceptanceCriterion::class, $project, $acceptanceCriterion]);

        return new AcceptanceCriterionResource($acceptanceCriterion->load([
            'requirement', 'verifier', 'approvedBy', 'supplierDecidedBy', 'clientDecidedBy',
        ]));
    }

    /**
     * Update an acceptance criterion.
     *
     * @response {"data": {"id": 1, "description": "Updated"}}
     */
    public function update(UpdateAcceptanceCriterionRequest $request, Project $project, AcceptanceCriterion $acceptanceCriterion): AcceptanceCriterionResource
    {
        $this->authorize('update', [AcceptanceCriterion::class, $project, $acceptanceCriterion]);

        $validated               = $request->validated();
        $validated['updated_by'] = auth()->user()->person_id;

        $acceptanceCriterion->applyVersionedChange($validated, auth()->user()->person_id);

        return new AcceptanceCriterionResource($acceptanceCriterion->fresh()->load(['requirement', 'verifier']));
    }

    /**
     * Delete an acceptance criterion (soft delete).
     *
     * @response 204 {}
     */
    public function destroy(Project $project, AcceptanceCriterion $acceptanceCriterion): Response
    {
        $this->authorize('delete', [AcceptanceCriterion::class, $project, $acceptanceCriterion]);

        $acceptanceCriterion->delete();

        return response()->noContent();
    }

    /**
     * Approve an acceptance criterion (marks the criterion definition itself as approved —
     * independent of the supplier/client pass-fail sign-off below).
     *
     * @response {"data": {"id": 1, "status": "approved"}}
     * @response 409 {"message": "Acceptance criterion is already approved."}
     */
    public function approve(Project $project, AcceptanceCriterion $acceptanceCriterion): AcceptanceCriterionResource
    {
        $this->authorize('approve', [AcceptanceCriterion::class, $project, $acceptanceCriterion]);

        abort_if(
            $acceptanceCriterion->status === AcceptanceCriterionStatus::Approved,
            409,
            'Acceptance criterion is already approved.'
        );

        $acceptanceCriterion->applyVersionedChange([
            'status'      => AcceptanceCriterionStatus::Approved->value,
            'approved_by' => auth()->user()->person_id,
            'approved_at' => now(),
            'updated_by'  => auth()->user()->person_id,
        ], auth()->user()->person_id);

        return new AcceptanceCriterionResource($acceptanceCriterion->fresh()->load(['approvedBy']));
    }

    /**
     * Record the supplier-side sign-off decision. This is a human judgment informed by
     * (but not bound to) the automated supplier_passed test signal — a note is required
     * only when the decision contradicts that signal (e.g. rejecting despite a pass).
     *
     * @response {"data": {"id": 1, "supplier_decision": "accepted"}}
     * @response 422 {"message": "A note is required when the decision contradicts the test result."}
     */
    public function supplierDecision(Request $request, Project $project, AcceptanceCriterion $acceptanceCriterion): AcceptanceCriterionResource
    {
        $this->authorize('decide', [AcceptanceCriterion::class, $project, $acceptanceCriterion]);

        $validated = $request->validate([
            'decision' => ['required', Rule::enum(AcceptanceCriterionDecision::class)],
            'note'     => ['nullable', 'string'],
        ]);

        $decision = $this->resolveDecision($validated, $acceptanceCriterion->supplier_passed);

        $acceptanceCriterion->applyVersionedChange([
            'supplier_decision'      => $decision['decision']->value,
            'supplier_decided_by'    => auth()->user()->person_id,
            'supplier_decided_at'    => now(),
            'supplier_decision_note' => $decision['note'],
            'updated_by'             => auth()->user()->person_id,
        ], auth()->user()->person_id, fn (AcceptanceCriterion $locked) => $locked->recomputeAccepted());

        return new AcceptanceCriterionResource($acceptanceCriterion->fresh()->load(['supplierDecidedBy', 'clientDecidedBy']));
    }

    /**
     * Record the client-side sign-off decision. Same rules as supplierDecision() but for
     * the client_passed signal.
     *
     * @response {"data": {"id": 1, "client_decision": "accepted"}}
     * @response 422 {"message": "A note is required when the decision contradicts the test result."}
     */
    public function clientDecision(Request $request, Project $project, AcceptanceCriterion $acceptanceCriterion): AcceptanceCriterionResource
    {
        $this->authorize('decide', [AcceptanceCriterion::class, $project, $acceptanceCriterion]);

        $validated = $request->validate([
            'decision' => ['required', Rule::enum(AcceptanceCriterionDecision::class)],
            'note'     => ['nullable', 'string'],
        ]);

        $decision = $this->resolveDecision($validated, $acceptanceCriterion->client_passed);

        $acceptanceCriterion->applyVersionedChange([
            'client_decision'      => $decision['decision']->value,
            'client_decided_by'    => auth()->user()->person_id,
            'client_decided_at'    => now(),
            'client_decision_note' => $decision['note'],
            'updated_by'           => auth()->user()->person_id,
        ], auth()->user()->person_id, fn (AcceptanceCriterion $locked) => $locked->recomputeAccepted());

        return new AcceptanceCriterionResource($acceptanceCriterion->fresh()->load(['supplierDecidedBy', 'clientDecidedBy']));
    }

    /**
     * Shared contradiction-check logic for both decision endpoints. Does not itself call
     * $request->validate() — that stays inline in each action method so Scramble's static
     * analysis (which only looks at the route method body, not delegated helpers) can
     * still infer the request body schema for the generated API docs.
     *
     * @param  array{decision: string, note: ?string}  $validated
     * @return array{decision: AcceptanceCriterionDecision, note: ?string}
     */
    private function resolveDecision(array $validated, bool $computedPassed): array
    {
        $decision = AcceptanceCriterionDecision::from($validated['decision']);

        abort_if($decision === AcceptanceCriterionDecision::Pending, 422, 'Decision must be accepted or rejected.');

        $contradictsComputedSignal = ($decision === AcceptanceCriterionDecision::Rejected && $computedPassed)
            || ($decision === AcceptanceCriterionDecision::Accepted && ! $computedPassed);

        abort_if(
            $contradictsComputedSignal && empty($validated['note']),
            422,
            'A note is required when the decision contradicts the test result.'
        );

        return ['decision' => $decision, 'note' => $validated['note'] ?? null];
    }

    private function assertRequirementBelongsToProject(Project $project, int $requirementId): void
    {
        $requirement = $project->requirements()->find($requirementId);

        abort_if(! $requirement, 422, 'Requirement must belong to this project.');

        abort_if(
            $requirement->type === RequirementType::Epic,
            422,
            'Acceptance criteria cannot be linked to an epic. Link to a classic requirement or user story.'
        );
    }
}
