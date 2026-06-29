<?php

namespace App\Documents\Metadata;

readonly class MeetingMinutesMetadata extends DocumentMetadata
{
    public function __construct(
        public ?string $meeting_date,
        public ?string $location,
        public ?int $chair_person_id,
        public array $attendee_ids,
    ) {}

    public static function from(array $data): static
    {
        return new static(
            meeting_date:    $data['meeting_date'] ?? null,
            location:        $data['location'] ?? null,
            chair_person_id: isset($data['chair_person_id']) ? (int) $data['chair_person_id'] : null,
            attendee_ids:    $data['attendee_ids'] ?? [],
        );
    }

    public function toArray(): array
    {
        $result = [];
        if ($this->meeting_date !== null) {
            $result['meeting_date'] = $this->meeting_date;
        }
        if ($this->location !== null) {
            $result['location'] = $this->location;
        }
        if ($this->chair_person_id !== null) {
            $result['chair_person_id'] = $this->chair_person_id;
        }
        if ($this->attendee_ids) {
            $result['attendee_ids'] = $this->attendee_ids;
        }

        return $result;
    }

    public static function rules(): array
    {
        return [
            'metadata.meeting_date'      => ['nullable', 'date'],
            'metadata.location'          => ['nullable', 'string', 'max:255'],
            'metadata.chair_person_id'   => ['nullable', 'integer', 'exists:people,id'],
            'metadata.attendee_ids'      => ['nullable', 'array'],
            'metadata.attendee_ids.*'    => ['integer', 'exists:people,id'],
        ];
    }
}
