<?php

namespace App\Documents;

use App\Enums\QaDocumentType;
use App\Models\CheckpointReport;
use App\Models\ExceptionReport;
use App\Models\HighlightReport;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Stage;

class DocumentLinkValidator
{
    private static array $allowedTypes = [
        Meeting::class          => [QaDocumentType::MeetingMinutes, QaDocumentType::MeetingAgenda],
        HighlightReport::class  => [QaDocumentType::HighlightReport],
        CheckpointReport::class => [QaDocumentType::CheckpointReport],
        ExceptionReport::class  => [QaDocumentType::ExceptionReport],
        Stage::class            => [QaDocumentType::StagePlan],
        Project::class          => [QaDocumentType::ProjectInitiationDocument],
    ];

    public static function isCompatible(string $entityClass, QaDocumentType $documentType): bool
    {
        $allowed = self::$allowedTypes[$entityClass] ?? [];
        return in_array($documentType, $allowed, true);
    }

    public static function allowedFor(string $entityClass): array
    {
        return array_map(
            fn (QaDocumentType $t) => $t->value,
            self::$allowedTypes[$entityClass] ?? []
        );
    }
}
