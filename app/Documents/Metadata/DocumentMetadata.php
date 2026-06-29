<?php

namespace App\Documents\Metadata;

readonly class DocumentMetadata
{
    public static function from(array $data): static
    {
        return new static();
    }

    public function toArray(): array
    {
        return [];
    }

    public static function rules(): array
    {
        return [];
    }
}
