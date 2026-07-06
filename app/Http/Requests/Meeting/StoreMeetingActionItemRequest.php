<?php

namespace App\Http\Requests\Meeting;

use App\Enums\MeetingActionItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeetingActionItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'owner_id'    => ['required', 'integer', Rule::exists('people', 'id')],
            'description' => ['required', 'string'],
            'due_date'    => ['nullable', 'date'],
            'status'      => ['nullable', Rule::enum(MeetingActionItemStatus::class)],
        ];
    }
}
