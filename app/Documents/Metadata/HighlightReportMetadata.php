<?php

namespace App\Documents\Metadata;

readonly class HighlightReportMetadata extends DocumentMetadata
{
    public function __construct(
        public ?string $reporting_period_start,
        public ?string $reporting_period_end,
        public ?bool $board_actions_required,
    ) {}

    public static function from(array $data): static
    {
        return new static(
            reporting_period_start: $data['reporting_period_start'] ?? null,
            reporting_period_end:   $data['reporting_period_end'] ?? null,
            board_actions_required: isset($data['board_actions_required']) ? (bool) $data['board_actions_required'] : null,
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
        if ($this->board_actions_required !== null) {
            $result['board_actions_required'] = $this->board_actions_required;
        }

        return $result;
    }

    public static function rules(): array
    {
        return [
            'metadata.reporting_period_start' => ['nullable', 'date'],
            'metadata.reporting_period_end'   => ['nullable', 'date', 'after_or_equal:metadata.reporting_period_start'],
            'metadata.board_actions_required' => ['nullable', 'boolean'],
        ];
    }
}
