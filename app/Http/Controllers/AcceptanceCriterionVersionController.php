<?php

namespace App\Http\Controllers;

use App\Http\Resources\AcceptanceCriterionVersionResource;
use App\Models\AcceptanceCriterion;
use App\Models\Project;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Acceptance Criterion Versions
 */
class AcceptanceCriterionVersionController extends Controller
{
    /**
     * List paginated version history for an acceptance criterion (newest first).
     *
     * @response {"data": [{"id": 1, "version_number": 1, "title": "..."}], "meta": {}, "links": {}}
     */
    public function index(Project $project, AcceptanceCriterion $acceptanceCriterion): AnonymousResourceCollection
    {
        $this->authorize('view', [AcceptanceCriterion::class, $project, $acceptanceCriterion]);

        abort_if($acceptanceCriterion->project_id !== $project->id, 404);

        $versions = $acceptanceCriterion->versions()
            ->with(['verifier', 'createdBy'])
            ->reorder('version_number', 'desc')
            ->paginate(25);

        return AcceptanceCriterionVersionResource::collection($versions);
    }
}
