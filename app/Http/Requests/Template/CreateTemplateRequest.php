<?php

namespace App\Http\Requests\Template;

use Illuminate\Foundation\Http\FormRequest;

class CreateTemplateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'exists:document_templates,id'],
            'name'      => ['required', 'string', 'max:255'],
            'category'  => ['nullable', 'string', 'max:100'],
            'type'      => ['nullable', 'string', 'max:100'],
            'settings'  => ['nullable', 'array'],
        ];
    }
}
