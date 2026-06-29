<?php

namespace App\Documents\Metadata;

readonly class RiskRegisterMetadata extends DocumentMetadata
{
    public function __construct(
        public ?string $review_date,
        public ?int $risk_owner_id,
    ) {}

    public static function from(array $data): static
    {
        return new static(
            review_date:   $data['review_date'] ?? null,
            risk_owner_id: isset($data['risk_owner_id']) ? (int) $data['risk_owner_id'] : null,
        );
    }

    public function toArray(): array
    {
        $result = [];
        if ($this->review_date !== null) {
            $result['review_date'] = $this->review_date;
        }
        if ($this->risk_owner_id !== null) {
            $result['risk_owner_id'] = $this->risk_owner_id;
        }

        return $result;
    }

    public static function rules(): array
    {
        return [
            'metadata.review_date'   => ['nullable', 'date'],
            'metadata.risk_owner_id' => ['nullable', 'integer', 'exists:people,id'],
        ];
    }
}
