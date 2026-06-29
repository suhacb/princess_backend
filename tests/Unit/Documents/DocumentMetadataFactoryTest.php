<?php

namespace Tests\Unit\Documents;

use App\Documents\Metadata\CheckpointReportMetadata;
use App\Documents\Metadata\DocumentMetadata;
use App\Documents\Metadata\DocumentMetadataFactory;
use App\Documents\Metadata\HighlightReportMetadata;
use App\Documents\Metadata\MeetingAgendaMetadata;
use App\Documents\Metadata\MeetingMinutesMetadata;
use App\Documents\Metadata\RiskRegisterMetadata;
use App\Enums\QaDocumentType;
use PHPUnit\Framework\TestCase;

class DocumentMetadataFactoryTest extends TestCase
{
    public function test_make_returns_base_dto_for_types_without_specific_metadata(): void
    {
        foreach ([
            QaDocumentType::ProjectBrief,
            QaDocumentType::StagePlan,
            QaDocumentType::RequirementsSpecification,
            QaDocumentType::General,
        ] as $type) {
            $dto = DocumentMetadataFactory::make($type, []);
            $this->assertInstanceOf(DocumentMetadata::class, $dto, "{$type->value} should use base DTO");
        }
    }

    public function test_make_returns_meeting_agenda_metadata(): void
    {
        $dto = DocumentMetadataFactory::make(QaDocumentType::MeetingAgenda, [
            'meeting_date'    => '2026-07-01',
            'location'        => 'Board Room A',
            'chair_person_id' => 5,
        ]);

        $this->assertInstanceOf(MeetingAgendaMetadata::class, $dto);
        $this->assertSame('2026-07-01', $dto->meeting_date);
        $this->assertSame('Board Room A', $dto->location);
        $this->assertSame(5, $dto->chair_person_id);
    }

    public function test_make_returns_meeting_minutes_metadata(): void
    {
        $dto = DocumentMetadataFactory::make(QaDocumentType::MeetingMinutes, [
            'meeting_date' => '2026-07-01',
            'attendee_ids' => [1, 2, 3],
        ]);

        $this->assertInstanceOf(MeetingMinutesMetadata::class, $dto);
        $this->assertSame('2026-07-01', $dto->meeting_date);
        $this->assertSame([1, 2, 3], $dto->attendee_ids);
    }

    public function test_make_returns_highlight_report_metadata(): void
    {
        $dto = DocumentMetadataFactory::make(QaDocumentType::HighlightReport, [
            'reporting_period_start' => '2026-06-01',
            'reporting_period_end'   => '2026-06-30',
            'board_actions_required' => true,
        ]);

        $this->assertInstanceOf(HighlightReportMetadata::class, $dto);
        $this->assertSame('2026-06-01', $dto->reporting_period_start);
        $this->assertTrue($dto->board_actions_required);
    }

    public function test_make_returns_checkpoint_report_metadata(): void
    {
        $dto = DocumentMetadataFactory::make(QaDocumentType::CheckpointReport, [
            'reporting_period_start' => '2026-06-01',
            'reporting_period_end'   => '2026-06-14',
        ]);

        $this->assertInstanceOf(CheckpointReportMetadata::class, $dto);
        $this->assertSame('2026-06-14', $dto->reporting_period_end);
    }

    public function test_make_returns_risk_register_metadata(): void
    {
        $dto = DocumentMetadataFactory::make(QaDocumentType::RiskRegister, [
            'review_date'   => '2026-08-01',
            'risk_owner_id' => 7,
        ]);

        $this->assertInstanceOf(RiskRegisterMetadata::class, $dto);
        $this->assertSame('2026-08-01', $dto->review_date);
        $this->assertSame(7, $dto->risk_owner_id);
    }

    public function test_toArray_omits_null_fields(): void
    {
        $dto = DocumentMetadataFactory::make(QaDocumentType::MeetingAgenda, [
            'meeting_date' => '2026-07-01',
        ]);

        $array = $dto->toArray();
        $this->assertArrayHasKey('meeting_date', $array);
        $this->assertArrayNotHasKey('location', $array);
        $this->assertArrayNotHasKey('chair_person_id', $array);
    }

    public function test_base_dto_toArray_is_empty(): void
    {
        $dto = DocumentMetadataFactory::make(QaDocumentType::General, ['ignored' => 'data']);
        $this->assertSame([], $dto->toArray());
    }

    public function test_rules_for_returns_empty_for_base_types(): void
    {
        $rules = DocumentMetadataFactory::rulesFor(QaDocumentType::ProjectBrief);
        $this->assertSame([], $rules);
    }

    public function test_rules_for_meeting_minutes_includes_attendee_ids(): void
    {
        $rules = DocumentMetadataFactory::rulesFor(QaDocumentType::MeetingMinutes);
        $this->assertArrayHasKey('metadata.attendee_ids', $rules);
        $this->assertArrayHasKey('metadata.attendee_ids.*', $rules);
        $this->assertArrayHasKey('metadata.chair_person_id', $rules);
    }

    public function test_rules_for_highlight_report_includes_period_and_actions(): void
    {
        $rules = DocumentMetadataFactory::rulesFor(QaDocumentType::HighlightReport);
        $this->assertArrayHasKey('metadata.reporting_period_start', $rules);
        $this->assertArrayHasKey('metadata.reporting_period_end', $rules);
        $this->assertArrayHasKey('metadata.board_actions_required', $rules);
    }
}
