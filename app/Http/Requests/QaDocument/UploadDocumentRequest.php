<?php

namespace App\Http\Requests\QaDocument;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = (int) config('princess.documents.upload_max_mb', 50) * 1024;

        return [
            'file'    => ['required', 'file', 'mimes:docx,odt', "max:{$maxKb}"],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
