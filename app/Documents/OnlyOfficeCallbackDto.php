<?php

namespace App\Documents;

readonly class OnlyOfficeCallbackDto
{
    public function __construct(
        public int $status,
        public string $key,
        public ?string $url,
    ) {}

    public static function from(array $payload): self
    {
        return new self(
            status: (int) ($payload['status'] ?? 0),
            key: (string) ($payload['key'] ?? ''),
            url: $payload['url'] ?? null,
        );
    }
}
