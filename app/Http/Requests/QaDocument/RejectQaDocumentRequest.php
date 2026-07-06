<?php

namespace App\Http\Requests\QaDocument;

use Illuminate\Foundation\Http\FormRequest;

class RejectQaDocumentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'review_notes' => ['required', 'string'],
        ];
    }
}
