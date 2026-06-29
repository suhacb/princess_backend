<?php

namespace App\Http\Requests\Template;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTemplateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'      => ['sometimes', 'string', 'max:255'],
            'category'  => ['nullable', 'string', 'max:100'],
            'type'      => ['nullable', 'string', 'max:100'],
            'settings'  => ['nullable', 'array'],
            'parent_id' => ['nullable', 'integer', 'exists:document_templates,id'],
        ];
    }
}
