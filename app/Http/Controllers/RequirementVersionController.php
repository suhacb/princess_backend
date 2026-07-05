<?php

namespace App\Http\Controllers;

use App\Http\Resources\RequirementVersionResource;
use App\Models\Project;
use App\Models\Requirement;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Requirement Versions
 */
class RequirementVersionController extends Controller
{
    /**
     * List paginated version history for a requirement (newest first).
     *
     * @response {"data": [{"id": 1, "version_number": 1, "title": "System shall log all user actions"}], "meta": {}, "links": {}}
     */
    public function index(Project $project, Requirement $requirement): AnonymousResourceCollection
    {
        $this->authorize('view', [Requirement::class, $project, $requirement]);

        abort_if($requirement->project_id !== $project->id, 404);

        $versions = $requirement->versions()
            ->with(['owner', 'createdBy'])
            ->reorder('version_number', 'desc')
            ->paginate(25);

        return RequirementVersionResource::collection($versions);
    }
}
