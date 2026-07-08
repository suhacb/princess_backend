<?php

namespace App\Http\Requests\PromptTemplate;

use Illuminate\Foundation\Http\FormRequest;

class StorePromptTemplateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ];
    }
}
