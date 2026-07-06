<?php

namespace App\Http\Requests\Meeting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeetingRequest extends FormRequest
{
    public function rules(): array
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
}
