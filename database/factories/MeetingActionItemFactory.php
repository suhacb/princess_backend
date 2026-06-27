<?php

namespace Database\Factories;

use App\Enums\MeetingActionItemStatus;
use App\Models\Meeting;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeetingActionItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'meeting_id'  => Meeting::factory(),
            'owner_id'    => Person::factory(),
            'description' => fake()->sentence(),
            'due_date'    => fake()->optional()->dateTimeBetween('now', '+2 months')?->format('Y-m-d'),
            'status'      => MeetingActionItemStatus::Open->value,
        ];
    }
}
