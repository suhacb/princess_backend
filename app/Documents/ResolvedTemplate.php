<?php

namespace App\Documents;

readonly class ResolvedTemplate
{
    public function __construct(
        public array $settings,
        public ?string $s3Key,
        public ?int $templateProjectId,
    ) {}

    public function hasFile(): bool
    {
        return $this->s3Key !== null;
    }
}
