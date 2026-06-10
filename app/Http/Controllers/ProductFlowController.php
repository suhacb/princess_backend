<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductDescription\ProductDependencyRequest;
use App\Models\Product;
use App\Models\ProductDependency;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductFlowController extends Controller
{
    /**
     * Return the full Product Flow Diagram as an adjacency list.
     *
     * @response {"products": [...], "dependencies": [{"predecessor_id": 1, "successor_id": 2}]}
     */
    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', [ProductDependency::class, $project]);

        $products     = $project->products()->get(['id', 'identifier', 'title', 'status', 'type']);
        $dependencies = ProductDependency::where('project_id', $project->id)
            ->get(['id', 'predecessor_id', 'successor_id']);

        return response()->json([
            'products'     => $products,
            'dependencies' => $dependencies,
        ]);
    }

    /**
     * Add a dependency between two products.
     *
     * @response 201 {"id": 1, "predecessor_id": 1, "successor_id": 2}
     */
    public function store(ProductDependencyRequest $request, Project $project): JsonResponse
    {
        $this->authorize('manage', [ProductDependency::class, $project]);

        $predecessorId = $request->integer('predecessor_id');
        $successorId   = $request->integer('successor_id');

        abort_if($predecessorId === $successorId, 422, 'A product cannot depend on itself.');

        abort_if(
            $project->products()->whereIn('id', [$predecessorId, $successorId])->count() !== 2,
            422,
            'Both products must belong to this project.'
        );

        abort_if(
            $this->wouldCreateCycle($predecessorId, $successorId, $project->id),
            422,
            'Adding this dependency would create a cycle in the Product Flow Diagram.'
        );

        $dependency = ProductDependency::create([
            'project_id'     => $project->id,
            'predecessor_id' => $predecessorId,
            'successor_id'   => $successorId,
        ]);

        return response()->json($dependency, 201);
    }

    /**
     * Remove a dependency.
     *
     * @response 204 {}
     */
    public function destroy(Project $project, ProductDependency $dependency): Response
    {
        $this->authorize('manage', [ProductDependency::class, $project]);

        abort_if($dependency->project_id !== $project->id, 404);

        $dependency->delete();

        return response()->noContent();
    }

    private function wouldCreateCycle(int $predecessorId, int $successorId, int $projectId): bool
    {
        $visited = [];
        $queue   = [$successorId];

        while (! empty($queue)) {
            $current = array_shift($queue);

            if ($current === $predecessorId) {
                return true;
            }

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            $nexts = ProductDependency::where('project_id', $projectId)
                ->where('predecessor_id', $current)
                ->pluck('successor_id')
                ->toArray();

            $queue = array_merge($queue, $nexts);
        }

        return false;
    }
}
