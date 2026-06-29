<?php

namespace App\Http\Requests\Template;

use App\Models\DocumentTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $project  = $this->route('project');
            $category = $this->input('category');
            $type     = $this->input('type');

            $exists = DocumentTemplate::where('project_id', $project->id)
                ->where(fn ($q) => $category ? $q->where('category', $category) : $q->whereNull('category'))
                ->where(fn ($q) => $type     ? $q->where('type', $type)         : $q->whereNull('type'))
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                $v->errors()->add('category', 'A template with this project, category, and type combination already exists.');
            }
        });
    }
}
