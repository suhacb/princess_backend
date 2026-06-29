<?php

namespace Tests\Unit\Enums;

use App\Enums\DocumentCategory;
use App\Enums\QaDocumentType;
use PHPUnit\Framework\TestCase;

class QaDocumentTypeTest extends TestCase
{
    public function test_initiation_types_return_initiation_category(): void
    {
        foreach ([
            QaDocumentType::ProjectBrief,
            QaDocumentType::ProjectInitiationDocument,
            QaDocumentType::ProjectProductDescription,
        ] as $type) {
            $this->assertSame(DocumentCategory::Initiation, $type->category(), "{$type->value} should be initiation");
        }
    }

    public function test_planning_types_return_planning_category(): void
    {
        foreach ([
            QaDocumentType::ProjectPlan,
            QaDocumentType::StagePlan,
            QaDocumentType::TeamPlan,
            QaDocumentType::ExceptionPlan,
            QaDocumentType::WorkPackage,
        ] as $type) {
            $this->assertSame(DocumentCategory::Planning, $type->category(), "{$type->value} should be planning");
        }
    }

    public function test_reporting_types_return_reporting_category(): void
    {
        foreach ([
            QaDocumentType::HighlightReport,
            QaDocumentType::CheckpointReport,
            QaDocumentType::EndStageReport,
            QaDocumentType::EndProjectReport,
            QaDocumentType::ExceptionReport,
            QaDocumentType::LessonsReport,
        ] as $type) {
            $this->assertSame(DocumentCategory::Reporting, $type->category(), "{$type->value} should be reporting");
        }
    }

    public function test_register_types_return_register_category(): void
    {
        foreach ([
            QaDocumentType::RiskRegister,
            QaDocumentType::IssueRegister,
            QaDocumentType::QualityRegister,
            QaDocumentType::ChangeLog,
            QaDocumentType::LessonsLog,
            QaDocumentType::DailyLog,
        ] as $type) {
            $this->assertSame(DocumentCategory::Register, $type->category(), "{$type->value} should be register");
        }
    }

    public function test_qa_types_return_qa_category(): void
    {
        foreach ([
            QaDocumentType::RequirementsSpecification,
            QaDocumentType::TestSpecification,
            QaDocumentType::TestSessionPlan,
            QaDocumentType::TestExecutionReport,
            QaDocumentType::TraceabilityMatrix,
        ] as $type) {
            $this->assertSame(DocumentCategory::Qa, $type->category(), "{$type->value} should be qa");
        }
    }

    public function test_meeting_types_return_meeting_category(): void
    {
        foreach ([
            QaDocumentType::MeetingAgenda,
            QaDocumentType::MeetingMinutes,
        ] as $type) {
            $this->assertSame(DocumentCategory::Meeting, $type->category(), "{$type->value} should be meeting");
        }
    }

    public function test_general_returns_general_category(): void
    {
        $this->assertSame(DocumentCategory::General, QaDocumentType::General->category());
    }

    public function test_all_types_have_a_category(): void
    {
        foreach (QaDocumentType::cases() as $type) {
            $this->assertInstanceOf(DocumentCategory::class, $type->category(), "{$type->value} must return a DocumentCategory");
        }
    }
}
