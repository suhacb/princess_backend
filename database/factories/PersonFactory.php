<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PersonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'         => fake()->name(),
            'email'        => fake()->unique()->safeEmail(),
            'phone'        => fake()->optional()->phoneNumber(),
            'organization' => fake()->optional()->company(),
            'job_title'    => fake()->optional()->jobTitle(),
            'notes'        => null,
        ];
    }
}
