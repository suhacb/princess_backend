<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\QaDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DocumentVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'document_id'     => QaDocument::factory(),
            'version_number'  => 1,
            's3_key'          => 'documents/1/versions/1/document.docx',
            'file_name'       => fake()->word() . '.docx',
            'file_size_bytes' => fake()->numberBetween(1024, 10 * 1024 * 1024),
            'onlyoffice_key'  => null,
            'converted_md_key' => null,
            'comment'         => null,
            'created_by'      => Person::factory(),
        ];
    }
}
