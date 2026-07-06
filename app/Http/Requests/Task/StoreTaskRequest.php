<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'           => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'stage_id'        => ['nullable', 'integer', Rule::exists('stages', 'id')],
            'work_package_id' => ['nullable', 'integer', Rule::exists('work_packages', 'id')],
            'assigned_to'     => ['nullable', 'integer', Rule::exists('people', 'id')],
            'due_date'        => ['nullable', 'date'],
            'status'          => ['nullable', Rule::enum(TaskStatus::class)],
            'priority'        => ['nullable', Rule::enum(TaskPriority::class)],
        ];
    }
}
