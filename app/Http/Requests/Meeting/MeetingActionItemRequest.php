<?php

namespace App\Http\Requests\Meeting;

use App\Enums\MeetingActionItemStatus;
use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class MeetingActionItemRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        return [
            'owner_id'    => ['required', 'integer', Rule::exists('people', 'id')],
            'description' => ['required', 'string'],
            'due_date'    => ['nullable', 'date'],
            'status'      => ['nullable', Rule::enum(MeetingActionItemStatus::class)],
        ];
    }

    public function rulesForUpdate(): array
    {
        return [
            'owner_id'    => ['sometimes', 'required', 'integer', Rule::exists('people', 'id')],
            'description' => ['sometimes', 'required', 'string'],
            'due_date'    => ['nullable', 'date'],
            'status'      => ['sometimes', 'required', Rule::enum(MeetingActionItemStatus::class)],
        ];
    }
}
