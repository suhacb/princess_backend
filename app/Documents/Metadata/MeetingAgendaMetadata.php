<?php

namespace App\Documents\Metadata;

readonly class MeetingAgendaMetadata extends DocumentMetadata
{
    public function __construct(
        public ?string $meeting_date,
        public ?string $location,
        public ?int $chair_person_id,
    ) {}

    public static function from(array $data): static
    {
        return new static(
            meeting_date:    $data['meeting_date'] ?? null,
            location:        $data['location'] ?? null,
            chair_person_id: isset($data['chair_person_id']) ? (int) $data['chair_person_id'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'meeting_date'    => $this->meeting_date,
            'location'        => $this->location,
            'chair_person_id' => $this->chair_person_id,
        ], fn ($v) => $v !== null);
    }

    public static function rules(): array
    {
        return [
            'metadata.meeting_date'    => ['nullable', 'date'],
            'metadata.location'        => ['nullable', 'string', 'max:255'],
            'metadata.chair_person_id' => ['nullable', 'integer', 'exists:people,id'],
        ];
    }
}
