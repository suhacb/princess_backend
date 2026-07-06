<?php

namespace App\Http\Requests\QaDocument;

use App\Documents\Metadata\DocumentMetadataFactory;
use App\Enums\QaDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQaDocumentRequest extends FormRequest
{
    public function rules(): array
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
            'documentable_type' => ['nullable', 'string', Rule::in(array_keys(DocumentableTypes::map()))],
            'documentable_id'   => ['nullable', 'integer', 'required_with:documentable_type'],
            'supersedes_id'     => ['nullable', 'integer', Rule::exists('qa_documents', 'id')],
            'requirement_ids'   => ['nullable', 'array'],
            'requirement_ids.*' => ['integer', Rule::exists('requirements', 'id')],
        ], $metadataRules);
    }
}
