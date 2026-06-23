<?php

namespace App\Http\Controllers;

use App\Enums\RequirementType;
use App\Models\AcceptanceCriterion;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\TestScenario;
use Illuminate\Http\JsonResponse;

/**
 * @tags Traceability
 */
class TraceabilityController extends Controller
{
    /**
     * Return the full requirements traceability matrix: requirements → acceptance criteria → test scenarios → results.
     *
     * @response {"data": [{"id": 1, "ref": "REQ-001", "acceptance_criteria": [{"ref": "AC-001", "test_scenarios": []}]}]}
     */
    public function index(Project $project): JsonResponse
    {
        $this->authorize('viewAny', [\App\Models\Requirement::class, $project]);

        // Load top-level requirements (non-user-stories): classics and epics
        $requirements = $project->requirements()
            ->whereIn('type', [RequirementType::Classic->value, RequirementType::Epic->value])
            ->whereNull('parent_id')
            ->with([
                'acceptanceCriteria.testScenarios',
                'children.acceptanceCriteria.testScenarios',
            ])
            ->get();

        return response()->json([
            'data' => $requirements->map(fn ($req) => $this->mapRequirement($req)),
        ]);
    }

    private function mapRequirement(Requirement $req): array
    {
        if ($req->type === RequirementType::Epic) {
            $userStories = $req->children->map(fn ($us) => $this->mapUserStory($us));
            return [
                'id'           => $req->id,
                'ref'          => $req->ref,
                'type'         => $req->type,
                'title'        => $req->title,
                'priority'     => $req->priority,
                'status'       => $req->status,
                'user_stories' => $userStories,
                'derived_status' => $this->derivedStatusFromUserStories($req->children),
            ];
        }

        $acs = $req->acceptanceCriteria->map(fn ($ac) => $this->mapAc($ac));
        return [
            'id'                 => $req->id,
            'ref'                => $req->ref,
            'type'               => $req->type,
            'title'              => $req->title,
            'priority'           => $req->priority,
            'status'             => $req->status,
            'acceptance_criteria' => $acs,
            'derived_status'     => $this->derivedStatusFromAcs($req->acceptanceCriteria),
        ];
    }

    private function mapUserStory(Requirement $us): array
    {
        $acs = $us->acceptanceCriteria->map(fn ($ac) => $this->mapAc($ac));
        return [
            'id'                 => $us->id,
            'ref'                => $us->ref,
            'title'              => $us->title,
            'role'               => $us->role,
            'status'             => $us->status,
            'acceptance_criteria' => $acs,
            'derived_status'     => $this->derivedStatusFromAcs($us->acceptanceCriteria),
        ];
    }

    private function mapAc(AcceptanceCriterion $ac): array
    {
        $scenarios = $ac->testScenarios->map(fn ($s) => $this->mapScenario($s));
        return [
            'id'              => $ac->id,
            'ref'             => $ac->ref,
            'description'     => $ac->description,
            'supplier_passed' => $ac->supplier_passed,
            'client_passed'   => $ac->client_passed,
            'accepted_at'     => $ac->accepted_at,
            'test_scenarios'  => $scenarios,
        ];
    }

    private function mapScenario(TestScenario $s): array
    {
        return [
            'id'                    => $s->id,
            'ref'                   => $s->ref,
            'title'                 => $s->title,
            'type'                  => $s->type,
            'is_testable'           => $s->is_testable,
            'latest_supplier_result' => $s->latestResultForTeam('supplier'),
            'latest_client_result'  => $s->latestResultForTeam('client'),
        ];
    }

    private function derivedStatusFromAcs($acs): string
    {
        if ($acs->isEmpty()) {
            return 'not_tested';
        }

        $allScenarios = $acs->flatMap->testScenarios;

        if ($allScenarios->isEmpty()) {
            return 'not_tested';
        }

        $hasAnyResult = $allScenarios->some(
            fn ($s) => $s->latestResultForTeam('supplier') !== null
                || $s->latestResultForTeam('client') !== null
        );

        if (! $hasAnyResult) {
            return 'not_tested';
        }

        $hasFail = $allScenarios->some(
            fn ($s) => in_array($s->latestResultForTeam('supplier'), ['fail'])
                || in_array($s->latestResultForTeam('client'), ['fail'])
        );

        if ($hasFail) {
            return 'failing';
        }

        $allAccepted = $acs->every(fn ($ac) => $ac->accepted_at !== null);
        if ($allAccepted) {
            return 'covered';
        }

        $anyAccepted = $acs->some(fn ($ac) => $ac->accepted_at !== null);
        if ($anyAccepted) {
            return 'partial';
        }

        return 'not_tested';
    }

    private function derivedStatusFromUserStories($userStories): string
    {
        if ($userStories->isEmpty()) {
            return 'not_tested';
        }

        $allAcs = $userStories->flatMap->acceptanceCriteria;
        return $this->derivedStatusFromAcs($allAcs);
    }
}
