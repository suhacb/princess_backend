<?php

namespace App\Http\Controllers;

use App\Documents\Metadata\DocumentMetadataFactory;
use App\Enums\QaDocumentStatus;
use App\Enums\QaDocumentType;
use App\Enums\RequirementStatus;
use App\Http\Requests\QaDocument\QaDocumentRequest;
use App\Http\Resources\QaDocumentResource;
use App\Models\Project;
use App\Models\QaDocument;
use App\Services\Document\TemplateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * @tags QA Documents
 */
class QaDocumentController extends Controller
{
    /**
     * List QA documents for a project.
     *
     * @queryParam type string Filter by type. Example: meeting_minutes
     * @queryParam category string Filter by category (initiation, planning, reporting, register, qa, meeting, general). Example: reporting
     * @queryParam status string Filter by status (draft, in_review, confirmed, superseded). Example: confirmed
     *
     * @response {"data": [{"id": 1, "type": "highlight_report", "category": "reporting", "status": "draft"}]}
     */
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [QaDocument::class, $project]);

        $query = $project->qaDocuments()->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return QaDocumentResource::collection($query->get());
    }

    /**
     * Create a QA document.
     *
     * @response 201 {"data": {"id": 1, "type": "highlight_report", "category": "reporting", "status": "draft"}}
     */
    public function store(QaDocumentRequest $request, Project $project): QaDocumentResource
    {
        $this->authorize('create', [QaDocument::class, $project]);

        $validated = $request->validated();
        $type      = QaDocumentType::from($validated['type']);

        $requirementIds = $validated['requirement_ids'] ?? [];
        unset($validated['requirement_ids']);

        // Resolve metadata through factory (validates shape, normalises).
        $metadata = DocumentMetadataFactory::make($type, $validated['metadata'] ?? [])->toArray();
        $validated['metadata'] = $metadata ?: null;

        // Resolve documentable alias to model class.
        $validated = $this->resolveDocumentable($validated);

        $this->assertItemIdsValid($project, $type, $requirementIds);
        $this->assertSupersedesValid($project, $validated['supersedes_id'] ?? null);

        $document = $project->qaDocuments()->create(array_merge($validated, [
            'status'     => QaDocumentStatus::Draft->value,
            'created_by' => auth()->user()->person_id,
        ]));

        if ($requirementIds) {
            $document->requirements()->sync($requirementIds);
        }

        try {
            app(TemplateService::class)->applyToDocument($document);
        } catch (\Throwable) {
            // Template application is best-effort; never block document creation.
        }

        return new QaDocumentResource($document->load(['requirements']));
    }

    /**
     * Get a QA document with its linked requirements.
     *
     * @response {"data": {"id": 1, "type": "highlight_report", "category": "reporting", "requirements": []}}
     */
    public function show(Project $project, QaDocument $qaDocument): QaDocumentResource
    {
        $this->authorize('view', [QaDocument::class, $project, $qaDocument]);

        return new QaDocumentResource($qaDocument->load(['requirements', 'supersedes', 'confirmedBy', 'documentable']));
    }

    /**
     * Update a QA document (not allowed if confirmed).
     *
     * @response {"data": {"id": 1, "title": "Updated"}}
     * @response 422 {"message": "A confirmed document cannot be edited."}
     */
    public function update(QaDocumentRequest $request, Project $project, QaDocument $qaDocument): QaDocumentResource
    {
        $this->authorize('update', [QaDocument::class, $project, $qaDocument]);

        abort_if(
            $qaDocument->status === QaDocumentStatus::Confirmed,
            422,
            'A confirmed document cannot be edited.'
        );

        $validated      = $request->validated();
        $requirementIds = $validated['requirement_ids'] ?? null;
        unset($validated['requirement_ids']);

        // Resolve metadata if provided.
        if (array_key_exists('metadata', $validated)) {
            $metadata = DocumentMetadataFactory::make($qaDocument->type, $validated['metadata'] ?? [])->toArray();
            $validated['metadata'] = $metadata ?: null;
        }

        // Resolve documentable alias to model class.
        $validated = $this->resolveDocumentable($validated);

        if ($requirementIds !== null) {
            $this->assertItemIdsValid($project, $qaDocument->type, $requirementIds);
            $qaDocument->requirements()->sync($requirementIds);
        }

        $qaDocument->update(array_merge($validated, [
            'updated_by' => auth()->user()->person_id,
        ]));

        return new QaDocumentResource($qaDocument->fresh()->load(['requirements']));
    }

    /**
     * Delete a QA document (soft delete).
     *
     * @response 204 {}
     */
    public function destroy(Project $project, QaDocument $qaDocument): Response
    {
        $this->authorize('delete', [QaDocument::class, $project, $qaDocument]);

        $qaDocument->delete();

        return response()->noContent();
    }

    /**
     * Send a draft QA document for review.
     *
     * @response {"data": {"id": 1, "status": "in_review"}}
     * @response 409 {"message": "Only draft documents can be sent for review."}
     */
    public function sendForReview(QaDocumentRequest $request, Project $project, QaDocument $qaDocument): QaDocumentResource
    {
        $this->authorize('sendForReview', [QaDocument::class, $project, $qaDocument]);

        abort_if(
            $qaDocument->status !== QaDocumentStatus::Draft,
            409,
            'Only draft documents can be sent for review.'
        );

        $qaDocument->update([
            'status'      => QaDocumentStatus::InReview->value,
            'reviewed_by' => auth()->user()->person_id,
            'reviewed_at' => now(),
            'updated_by'  => auth()->user()->person_id,
        ]);

        return new QaDocumentResource($qaDocument->fresh());
    }

    /**
     * Reject a QA document under review (returns it to draft).
     *
     * @response {"data": {"id": 1, "status": "draft"}}
     * @response 409 {"message": "Only documents in review can be rejected."}
     */
    public function reject(QaDocumentRequest $request, Project $project, QaDocument $qaDocument): QaDocumentResource
    {
        $this->authorize('reject', [QaDocument::class, $project, $qaDocument]);

        abort_if(
            $qaDocument->status !== QaDocumentStatus::InReview,
            409,
            'Only documents in review can be rejected.'
        );

        $qaDocument->update(array_merge($request->validated(), [
            'status'     => QaDocumentStatus::Draft->value,
            'updated_by' => auth()->user()->person_id,
        ]));

        return new QaDocumentResource($qaDocument->fresh());
    }

    /**
     * Confirm a QA document under review. Also approves linked reviewed requirements for requirements_specification type.
     *
     * @response {"data": {"id": 1, "status": "confirmed"}}
     * @response 409 {"message": "Only documents in review can be confirmed."}
     */
    public function confirm(Project $project, QaDocument $qaDocument): QaDocumentResource
    {
        $this->authorize('confirm', [QaDocument::class, $project, $qaDocument]);

        abort_if(
            $qaDocument->status !== QaDocumentStatus::InReview,
            409,
            'Only documents in review can be confirmed.'
        );

        DB::transaction(function () use ($qaDocument) {
            if ($qaDocument->type === QaDocumentType::RequirementsSpecification) {
                $qaDocument->requirements()
                    ->where('status', RequirementStatus::Reviewed->value)
                    ->each(function ($requirement) {
                        $requirement->update([
                            'status'      => RequirementStatus::Approved->value,
                            'approved_by' => auth()->user()->person_id,
                            'approved_at' => now(),
                            'updated_by'  => auth()->user()->person_id,
                        ]);
                    });
            }

            if ($qaDocument->supersedes_id) {
                QaDocument::where('id', $qaDocument->supersedes_id)
                    ->update(['status' => QaDocumentStatus::Superseded->value]);
            }

            $qaDocument->update([
                'status'       => QaDocumentStatus::Confirmed->value,
                'confirmed_by' => auth()->user()->person_id,
                'confirmed_at' => now(),
                'updated_by'   => auth()->user()->person_id,
            ]);
        });

        return new QaDocumentResource($qaDocument->fresh()->load(['requirements', 'confirmedBy']));
    }

    private function resolveDocumentable(array $validated): array
    {
        if (isset($validated['documentable_type'])) {
            $types = QaDocumentRequest::documentableTypes();
            $validated['documentable_type'] = $types[$validated['documentable_type']] ?? $validated['documentable_type'];
        }

        return $validated;
    }

    private function assertItemIdsValid(Project $project, QaDocumentType $type, array $requirementIds): void
    {
        if ($type !== QaDocumentType::RequirementsSpecification && count($requirementIds) > 0) {
            abort(422, 'Requirement IDs can only be attached to requirements_specification documents.');
        }

        if (count($requirementIds) > 0) {
            $found = $project->requirements()->whereIn('id', $requirementIds)->count();
            abort_if(
                $found !== count($requirementIds),
                422,
                'All requirements must belong to this project.'
            );
        }
    }

    private function assertSupersedesValid(Project $project, ?int $supersedesId): void
    {
        if (! $supersedesId) {
            return;
        }

        $old = $project->qaDocuments()
            ->where('id', $supersedesId)
            ->where('status', QaDocumentStatus::Confirmed->value)
            ->first();

        abort_if(! $old, 422, 'The superseded document must be a confirmed document belonging to this project.');
    }
}
