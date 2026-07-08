<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromptTemplate\StorePromptTemplateRequest;
use App\Http\Resources\PromptTemplateResource;
use App\Models\PromptTemplate;
use App\Services\Llm\PromptTemplateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Prompt Templates
 */
class PromptTemplateController extends Controller
{
    public function __construct(private readonly PromptTemplateService $promptTemplateService) {}

    /**
     * List prompt templates. Without `name`, returns the active version of each template.
     * With `name`, returns all versions of that template, newest first.
     *
     * @queryParam name string Filter to all versions of a specific template. Example: requirements-extraction
     *
     * @response {"data": [{"id": 1, "name": "requirements-extraction", "version": 1, "active": true}]}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PromptTemplate::class);

        if ($request->filled('name')) {
            $templates = PromptTemplate::where('name', $request->name)->latest('version')->paginate(25);

            return PromptTemplateResource::collection($templates);
        }

        $templates = PromptTemplate::active()->orderBy('name')->paginate(25);

        return PromptTemplateResource::collection($templates);
    }

    /**
     * Create a new version of a prompt template. If a template with this name
     * already has an active version, it is deactivated and this becomes the new active version.
     *
     * @response 201 {"data": {"id": 2, "name": "requirements-extraction", "version": 2, "active": true}}
     * @response 422 {"message": "The name field is required."}
     */
    public function store(StorePromptTemplateRequest $request): PromptTemplateResource
    {
        $this->authorize('create', PromptTemplate::class);

        $validated = $request->validated();

        $template = $this->promptTemplateService->createVersion(
            $validated['name'],
            $validated['body'],
            auth()->user()->person_id,
        );

        return new PromptTemplateResource($template->load('createdBy'));
    }

    /**
     * Get a specific prompt template version.
     *
     * @response {"data": {"id": 1, "name": "requirements-extraction", "version": 1, "active": true}}
     */
    public function show(PromptTemplate $promptTemplate): PromptTemplateResource
    {
        $this->authorize('view', $promptTemplate);

        return new PromptTemplateResource($promptTemplate->load('createdBy'));
    }
}
