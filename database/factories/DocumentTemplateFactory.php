<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'parent_id'  => null,
            'name'       => fake()->words(3, true),
            'category'   => null,
            'type'       => null,
            's3_key'     => null,
            'settings'   => [],
            'created_by' => Person::factory(),
        ];
    }

    public function global(): static
    {
        return $this->state(['project_id' => null]);
    }

    public function withFile(): static
    {
        return $this->state(fn (array $attrs) => [
            's3_key' => 'templates/' . ($attrs['id'] ?? 1) . '/original.docx',
        ]);
    }
}
