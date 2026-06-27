<?php

namespace App\Http\Requests\Meeting;

use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class MeetingRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        return [
            'title'          => ['required', 'string', 'max:255'],
            'date_time'      => ['required', 'date'],
            'agenda'         => ['nullable', 'string'],
            'minutes_body'   => ['nullable', 'string'],
            'attendee_ids'   => ['nullable', 'array'],
            'attendee_ids.*' => ['integer', Rule::exists('people', 'id')],
        ];
    }

    public function rulesForUpdate(): array
    {
        return [
            'title'          => ['sometimes', 'required', 'string', 'max:255'],
            'date_time'      => ['sometimes', 'required', 'date'],
            'agenda'         => ['nullable', 'string'],
            'minutes_body'   => ['nullable', 'string'],
            'attendee_ids'   => ['nullable', 'array'],
            'attendee_ids.*' => ['integer', Rule::exists('people', 'id')],
        ];
    }
}
