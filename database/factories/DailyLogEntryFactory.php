<?php

namespace Database\Factories;

use App\Enums\DailyLogEntryType;
use App\Enums\DailyLogSource;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class DailyLogEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'stage_id'   => null,
            'date'       => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'entry_type' => fake()->randomElement(DailyLogEntryType::cases())->value,
            'body'       => fake()->paragraph(),
            'author_id'  => Person::factory(),
            'source'     => DailyLogSource::Manual->value,
        ];
    }
}
