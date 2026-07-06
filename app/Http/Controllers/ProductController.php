<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Http\Requests\ProductDescription\StoreProductRequest;
use App\Http\Requests\ProductDescription\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @tags Products
 */
class ProductController extends Controller
{
    /**
     * List all products (flat, with parent_id for client-side tree).
     *
     * @response {"data": [{"id": 1, "title": "...", "parent_id": null}]}
     */
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Product::class, $project]);

        return ProductResource::collection($project->products()->latest()->get());
    }

    /**
     * Return the Product Breakdown Structure as a nested tree.
     *
     * @response {"data": [{"id": 1, "title": "...", "children": [...]}]}
     */
    public function tree(Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Product::class, $project]);

        $all = $project->products()->get();

        $all->each(function (Product $product) use ($all) {
            $product->setRelation(
                'children',
                $all->filter(fn(Product $p) => $p->parent_id === $product->id)->values()
            );
        });

        $roots = $all->filter(fn(Product $p) => $p->parent_id === null)->values();

        return ProductResource::collection($roots);
    }

    /**
     * Create a product.
     *
     * @response 201 {"data": {"id": 1, "title": "...", "status": "draft"}}
     */
    public function store(StoreProductRequest $request, Project $project): ProductResource
    {
        $this->authorize('create', [Product::class, $project]);

        $validated = $request->validated();

        if (isset($validated['parent_id'])) {
            abort_if(
                ! $project->products()->where('id', $validated['parent_id'])->exists(),
                422,
                'Parent product must belong to the same project.'
            );
        }

        $product = $project->products()->create(array_merge(
            $validated,
            [
                'status'     => ProductStatus::Draft->value,
                'created_by' => auth()->user()->person_id,
            ]
        ));

        return new ProductResource($product);
    }

    /**
     * Get a product.
     *
     * @response {"data": {"id": 1, "title": "..."}}
     */
    public function show(Project $project, Product $product): ProductResource
    {
        $this->authorize('view', [Product::class, $project, $product]);

        return new ProductResource($product);
    }

    /**
     * Update a product.
     *
     * @response {"data": {"id": 1, "title": "Updated"}}
     */
    public function update(UpdateProductRequest $request, Project $project, Product $product): ProductResource
    {
        $this->authorize('update', [Product::class, $project, $product]);

        $validated = $request->validated();

        if (isset($validated['parent_id'])) {
            abort_if(
                ! $project->products()->where('id', $validated['parent_id'])->exists(),
                422,
                'Parent product must belong to the same project.'
            );
        }

        $product->update(array_merge(
            $validated,
            ['updated_by' => auth()->user()->person_id]
        ));

        return new ProductResource($product);
    }

    /**
     * Delete a product.
     *
     * @response 204 {}
     */
    public function destroy(Project $project, Product $product): Response
    {
        $this->authorize('delete', [Product::class, $project, $product]);

        $product->delete();

        return response()->noContent();
    }

    /**
     * Baseline a product — locks it as the approved version.
     *
     * @response {"data": {"id": 1, "status": "baselined"}}
     */
    public function baseline(Project $project, Product $product): ProductResource
    {
        $this->authorize('baseline', [Product::class, $project, $product]);

        $product->update([
            'status'       => ProductStatus::Baselined->value,
            'baselined_at' => now(),
            'updated_by'   => auth()->user()->person_id,
        ]);

        return new ProductResource($product);
    }
}
