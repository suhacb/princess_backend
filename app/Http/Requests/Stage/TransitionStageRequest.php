<?php

namespace App\Http\Requests\Stage;

use App\Enums\StageStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionStageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(StageStatus::class)],
        ];
    }
}
