<?php

namespace App\Http\Requests\QaDocument;

use App\Enums\QaDocumentType;
use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class QaDocumentRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        return [
            'type'              => ['required', Rule::enum(QaDocumentType::class)],
            'title'             => ['required', 'string', 'max:255'],
            'version'           => ['nullable', 'string', 'max:50'],
            'description'       => ['nullable', 'string'],
            'file_name'         => ['nullable', 'string', 'max:255'],
            'file_reference'    => ['nullable', 'string', 'max:1000'],
            'supersedes_id'     => ['nullable', 'integer', Rule::exists('qa_documents', 'id')],
            'requirement_ids'   => ['nullable', 'array'],
            'requirement_ids.*' => ['integer', Rule::exists('requirements', 'id')],
        ];
    }

    public function rulesForUpdate(): array
    {
        return [
            'title'             => ['sometimes', 'required', 'string', 'max:255'],
            'version'           => ['nullable', 'string', 'max:50'],
            'description'       => ['nullable', 'string'],
            'file_name'         => ['nullable', 'string', 'max:255'],
            'file_reference'    => ['nullable', 'string', 'max:1000'],
            'requirement_ids'   => ['nullable', 'array'],
            'requirement_ids.*' => ['integer', Rule::exists('requirements', 'id')],
        ];
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
