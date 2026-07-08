<?php

namespace Database\Factories;

use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromptTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'       => fake()->unique()->slug(2),
            'version'    => 1,
            'body'       => 'Hi {{name}}',
            'created_by' => Person::factory(),
            'active'     => true,
        ];
    }
}
