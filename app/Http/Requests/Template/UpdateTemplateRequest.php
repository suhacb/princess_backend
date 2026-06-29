<?php

namespace App\Http\Requests\Template;

use App\Models\DocumentTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $project  = $this->route('project');
            $template = $this->route('template');

            // Only check when category or type is being changed.
            if (! $this->has('category') && ! $this->has('type')) {
                return;
            }

            $category = $this->has('category') ? $this->input('category') : $template->category;
            $type     = $this->has('type')     ? $this->input('type')     : $template->type;

            $exists = DocumentTemplate::where('project_id', $project->id)
                ->where('id', '!=', $template->id)
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
