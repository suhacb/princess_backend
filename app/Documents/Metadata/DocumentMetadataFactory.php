<?php

namespace App\Documents\Metadata;

use App\Enums\QaDocumentType;

class DocumentMetadataFactory
{
    public static function make(QaDocumentType $type, array $data): DocumentMetadata
    {
        return match ($type) {
            QaDocumentType::MeetingAgenda  => MeetingAgendaMetadata::from($data),
            QaDocumentType::MeetingMinutes => MeetingMinutesMetadata::from($data),
            QaDocumentType::HighlightReport  => HighlightReportMetadata::from($data),
            QaDocumentType::CheckpointReport => CheckpointReportMetadata::from($data),
            QaDocumentType::RiskRegister     => RiskRegisterMetadata::from($data),
            default                          => DocumentMetadata::from($data),
        };
    }

    public static function rulesFor(QaDocumentType $type): array
    {
        return match ($type) {
            QaDocumentType::MeetingAgenda    => MeetingAgendaMetadata::rules(),
            QaDocumentType::MeetingMinutes   => MeetingMinutesMetadata::rules(),
            QaDocumentType::HighlightReport  => HighlightReportMetadata::rules(),
            QaDocumentType::CheckpointReport => CheckpointReportMetadata::rules(),
            QaDocumentType::RiskRegister     => RiskRegisterMetadata::rules(),
            default                          => DocumentMetadata::rules(),
        };
    }
}
