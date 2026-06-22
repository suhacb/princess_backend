<?php

namespace Database\Factories;

use App\Enums\QaDocumentStatus;
use App\Enums\QaDocumentType;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class QaDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'     => Project::factory(),
            'type'           => QaDocumentType::RequirementsSpecification->value,
            'title'          => fake()->sentence(4),
            'version'        => 'v1.0',
            'description'    => fake()->optional()->paragraph(),
            'file_name'      => null,
            'file_reference' => null,
            'status'         => QaDocumentStatus::Draft->value,
            'supersedes_id'  => null,
            'created_by'     => Person::factory(),
        ];
    }

    public function inReview(): static
    {
        return $this->state(['status' => QaDocumentStatus::InReview->value]);
    }

    public function confirmed(): static
    {
        return $this->state(['status' => QaDocumentStatus::Confirmed->value]);
    }
}
