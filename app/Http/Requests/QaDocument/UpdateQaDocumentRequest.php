<?php

namespace App\Http\Requests\QaDocument;

use App\Documents\Metadata\DocumentMetadataFactory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQaDocumentRequest extends FormRequest
{
    public function rules(): array
    {
        $type          = $this->route('qaDocument')?->type;
        $metadataRules = $type ? DocumentMetadataFactory::rulesFor($type) : [];

        return array_merge([
            'title'             => ['sometimes', 'required', 'string', 'max:255'],
            'version'           => ['nullable', 'string', 'max:50'],
            'description'       => ['nullable', 'string'],
            'metadata'          => ['nullable', 'array'],
            'documentable_type' => ['nullable', 'string', Rule::in(array_keys(DocumentableTypes::map()))],
            'documentable_id'   => ['nullable', 'integer', 'required_with:documentable_type'],
            'requirement_ids'   => ['nullable', 'array'],
            'requirement_ids.*' => ['integer', Rule::exists('requirements', 'id')],
        ], $metadataRules);
    }
}
