<?php

namespace App\Http\Requests\QaDocument;

use App\Documents\Metadata\DocumentMetadataFactory;
use App\Enums\QaDocumentType;
use App\Http\Requests\DynamicRequest;
use App\Models\CheckpointReport;
use App\Models\ExceptionReport;
use App\Models\HighlightReport;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Stage;
use Illuminate\Validation\Rule;

class QaDocumentRequest extends DynamicRequest
{
    private const DOCUMENTABLE_TYPES = [
        'meeting'           => Meeting::class,
        'highlight_report'  => HighlightReport::class,
        'checkpoint_report' => CheckpointReport::class,
        'exception_report'  => ExceptionReport::class,
        'stage'             => Stage::class,
        'project'           => Project::class,
    ];

    public static function documentableTypes(): array
    {
        return self::DOCUMENTABLE_TYPES;
    }

    public function rulesForStore(): array
    {
        $metadataRules = [];
        if ($this->filled('type') && QaDocumentType::tryFrom($this->input('type'))) {
            $metadataRules = DocumentMetadataFactory::rulesFor(QaDocumentType::from($this->input('type')));
        }

        return array_merge([
            'type'              => ['required', Rule::enum(QaDocumentType::class)],
            'title'             => ['required', 'string', 'max:255'],
            'version'           => ['nullable', 'string', 'max:50'],
            'description'       => ['nullable', 'string'],
            'metadata'          => ['nullable', 'array'],
            'documentable_type' => ['nullable', 'string', Rule::in(array_keys(self::DOCUMENTABLE_TYPES))],
            'documentable_id'   => ['nullable', 'integer', 'required_with:documentable_type'],
            'supersedes_id'     => ['nullable', 'integer', Rule::exists('qa_documents', 'id')],
            'requirement_ids'   => ['nullable', 'array'],
            'requirement_ids.*' => ['integer', Rule::exists('requirements', 'id')],
        ], $metadataRules);
    }

    public function rulesForUpdate(): array
    {
        $type          = $this->route('qaDocument')?->type;
        $metadataRules = $type ? DocumentMetadataFactory::rulesFor($type) : [];

        return array_merge([
            'title'             => ['sometimes', 'required', 'string', 'max:255'],
            'version'           => ['nullable', 'string', 'max:50'],
            'description'       => ['nullable', 'string'],
            'metadata'          => ['nullable', 'array'],
            'documentable_type' => ['nullable', 'string', Rule::in(array_keys(self::DOCUMENTABLE_TYPES))],
            'documentable_id'   => ['nullable', 'integer', 'required_with:documentable_type'],
            'requirement_ids'   => ['nullable', 'array'],
            'requirement_ids.*' => ['integer', Rule::exists('requirements', 'id')],
        ], $metadataRules);
    }

    public function rulesForSendForReview(): array
    {
        return [];
    }

    public function rulesForReject(): array
    {
        return [
            'review_notes' => ['required', 'string'],
        ];
    }
}
