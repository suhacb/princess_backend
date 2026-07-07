<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\TestSessionResult;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestSessionResultAttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'test_session_result_id' => TestSessionResult::factory(),
            'step_index'             => null,
            's3_key'                 => 'test-session-results/1/attachments/' . fake()->uuid() . '.png',
            'file_name'              => fake()->word() . '.png',
            'file_size_bytes'        => fake()->numberBetween(1024, 5 * 1024 * 1024),
            'mime_type'              => 'image/png',
            'created_by'             => Person::factory(),
        ];
    }
}
