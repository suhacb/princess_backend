<?php

namespace App\Documents\Metadata;

readonly class CheckpointReportMetadata extends DocumentMetadata
{
    public function __construct(
        public ?string $reporting_period_start,
        public ?string $reporting_period_end,
    ) {}

    public static function from(array $data): static
    {
        return new static(
            reporting_period_start: $data['reporting_period_start'] ?? null,
            reporting_period_end:   $data['reporting_period_end'] ?? null,
        );
    }

    public function toArray(): array
    {
        $result = [];
        if ($this->reporting_period_start !== null) {
            $result['reporting_period_start'] = $this->reporting_period_start;
        }
        if ($this->reporting_period_end !== null) {
            $result['reporting_period_end'] = $this->reporting_period_end;
        }

        return $result;
    }

    public static function rules(): array
    {
        return [
            'metadata.reporting_period_start' => ['nullable', 'date'],
            'metadata.reporting_period_end'   => ['nullable', 'date', 'after_or_equal:metadata.reporting_period_start'],
        ];
    }
}
