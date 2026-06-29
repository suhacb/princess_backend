<?php

namespace App\Services\Document;

use App\Documents\ResolvedTemplate;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\QaDocument;
use Illuminate\Support\Facades\DB;

class TemplateService
{
    public function __construct(
        private readonly GarageStorageService $storage,
    ) {}

    /**
     * Resolve a template for the given project, category, and type.
     *
     * Resolution order (first match wins for s3_key; settings deep-merged top-down):
     *   global-root → global-category → global-type → project-root → project-category → project-type
     *
     * Settings are merged child-over-parent (project-type wins over everything else).
     * s3_key is NOT inherited — only a template that explicitly has a file contributes it,
     * and the most specific match (highest priority) wins.
     */
    public function resolve(Project $project, string $category, string $type): ?ResolvedTemplate
    {
        $candidates = DocumentTemplate::whereNull('deleted_at')
            ->where(function ($q) use ($project, $category, $type) {
                // global root
                $q->orWhere(fn ($s) => $s->whereNull('project_id')->whereNull('category')->whereNull('type'));
                // global category
                $q->orWhere(fn ($s) => $s->whereNull('project_id')->where('category', $category)->whereNull('type'));
                // global type
                $q->orWhere(fn ($s) => $s->whereNull('project_id')->where('category', $category)->where('type', $type));
                // project root
                $q->orWhere(fn ($s) => $s->where('project_id', $project->id)->whereNull('category')->whereNull('type'));
                // project category
                $q->orWhere(fn ($s) => $s->where('project_id', $project->id)->where('category', $category)->whereNull('type'));
                // project type
                $q->orWhere(fn ($s) => $s->where('project_id', $project->id)->where('category', $category)->where('type', $type));
            })
            ->get()
            ->keyBy(fn (DocumentTemplate $t) => $this->priority($t, $project->id, $category, $type));

        if ($candidates->isEmpty()) {
            return null;
        }

        // Priority order: 0=global-root … 5=project-type. Merge settings from lowest to highest priority.
        $mergedSettings = [];
        $resolvedS3Key  = null;
        $resolvedProjectId = null;

        foreach (range(0, 5) as $priority) {
            /** @var DocumentTemplate|null $template */
            $template = $candidates->get($priority);
            if ($template === null) {
                continue;
            }

            $mergedSettings = $this->deepMerge($mergedSettings, $template->settings ?? []);

            // Highest-priority template with a file wins.
            if ($template->s3_key !== null) {
                $resolvedS3Key     = $template->s3_key;
                $resolvedProjectId = $template->project_id;
            }
        }

        return new ResolvedTemplate(
            settings: $mergedSettings,
            s3Key: $resolvedS3Key,
            templateProjectId: $resolvedProjectId,
        );
    }

    /**
     * Copy a resolved template file into the project bucket as version 1 of a new document.
     * Returns null if no template is found or if the template has no file.
     */
    public function applyToDocument(QaDocument $document): ?DocumentVersion
    {
        $category = $document->category?->value ?? '';
        $type     = $document->type?->value ?? '';

        $resolved = $this->resolve($document->project, $category, $type);

        if ($resolved === null || ! $resolved->hasFile()) {
            return null;
        }

        $project  = $document->project;
        $destKey  = "documents/{$document->id}/versions/1/original.docx";
        $fileName = basename($resolved->s3Key);

        return DB::transaction(function () use ($project, $document, $resolved, $destKey, $fileName) {
            if ($resolved->templateProjectId !== null) {
                // Same-project template: single S3 copy, no download/re-upload.
                $this->storage->copy($project, $resolved->s3Key, $destKey);
                $fileSize = $this->storage->size($project, $destKey);
            } else {
                // Global template lives in the templates bucket; stream it across.
                $contents = $this->storage->getFromTemplates($resolved->s3Key);
                $this->storage->put($project, $destKey, $contents);
                $fileSize = strlen($contents);
            }

            $version = DocumentVersion::create([
                'document_id'     => $document->id,
                'version_number'  => 1,
                's3_key'          => $destKey,
                'file_name'       => $fileName,
                'file_size_bytes' => $fileSize,
                'comment'         => 'Applied from template',
                'created_by'      => $document->created_by,
            ]);

            $document->update(['current_version_id' => $version->id]);

            return $version;
        });
    }

    private function priority(DocumentTemplate $template, int $projectId, string $category, string $type): int
    {
        $isGlobal   = $template->project_id === null;
        $isProject  = $template->project_id === $projectId;
        $isCategory = $template->category === $category;
        $isType     = $template->type === $type;

        return match (true) {
            $isGlobal  && ! $isCategory              => 0, // global root
            $isGlobal  && $isCategory && ! $isType   => 1, // global category
            $isGlobal  && $isCategory && $isType     => 2, // global type
            $isProject && ! $isCategory              => 3, // project root
            $isProject && $isCategory && ! $isType   => 4, // project category
            $isProject && $isCategory && $isType     => 5, // project type
            default                                  => 0,
        };
    }

    private function deepMerge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
