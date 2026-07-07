<?php

namespace App\Http\Requests\TestSession;

use Illuminate\Foundation\Http\FormRequest;

class UploadTestSessionResultAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = (int) config('princess.attachments.upload_max_mb', 10) * 1024;

        return [
            'file'       => ['required', 'file', 'mimes:png,jpg,jpeg,gif,pdf,webp', "max:{$maxKb}"],
            'step_index' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
