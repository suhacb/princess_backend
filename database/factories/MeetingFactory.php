<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeetingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'   => Project::factory(),
            'title'        => fake()->sentence(3),
            'date_time'    => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d H:i:s'),
            'agenda'       => fake()->optional()->paragraph(),
            'minutes_body' => fake()->optional()->paragraph(),
            'created_by'   => Person::factory(),
        ];
    }
}
