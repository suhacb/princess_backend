<?php

namespace App\Http\Requests\Meeting;

use App\Enums\MeetingActionItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeetingActionItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'owner_id'    => ['sometimes', 'required', 'integer', Rule::exists('people', 'id')],
            'description' => ['sometimes', 'required', 'string'],
            'due_date'    => ['nullable', 'date'],
            'status'      => ['sometimes', 'required', Rule::enum(MeetingActionItemStatus::class)],
        ];
    }
}
