<?php

namespace App\Http\Requests\Template;

use Illuminate\Foundation\Http\FormRequest;

class UploadTemplateRequest extends FormRequest
{
    public function rules(): array
    {
        $maxKb = (int) config('princess.documents.upload_max_mb', 50) * 1024;

        return [
            'file' => ['required', 'file', 'mimes:docx', "max:{$maxKb}"],
        ];
    }
}
