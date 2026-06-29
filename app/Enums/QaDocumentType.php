<?php

namespace App\Enums;

enum QaDocumentType: string
{
    // Initiation
    case ProjectBrief              = 'project_brief';
    case ProjectInitiationDocument = 'project_initiation_document';
    case ProjectProductDescription = 'project_product_description';

    // Planning
    case ProjectPlan   = 'project_plan';
    case StagePlan     = 'stage_plan';
    case TeamPlan      = 'team_plan';
    case ExceptionPlan = 'exception_plan';
    case WorkPackage   = 'work_package';

    // Reporting
    case HighlightReport  = 'highlight_report';
    case CheckpointReport = 'checkpoint_report';
    case EndStageReport   = 'end_stage_report';
    case EndProjectReport = 'end_project_report';
    case ExceptionReport  = 'exception_report';
    case LessonsReport    = 'lessons_report';

    // Register
    case RiskRegister    = 'risk_register';
    case IssueRegister   = 'issue_register';
    case QualityRegister = 'quality_register';
    case ChangeLog       = 'change_log';
    case LessonsLog      = 'lessons_log';
    case DailyLog        = 'daily_log';

    // QA
    case RequirementsSpecification = 'requirements_specification';
    case TestSpecification         = 'test_specification';
    case TestSessionPlan           = 'test_session_plan';
    case TestExecutionReport       = 'test_execution_report';
    case TraceabilityMatrix        = 'traceability_matrix';

    // Meeting
    case MeetingAgenda  = 'meeting_agenda';
    case MeetingMinutes = 'meeting_minutes';

    // General
    case General = 'general';

    public function category(): DocumentCategory
    {
        return match ($this) {
            self::ProjectBrief,
            self::ProjectInitiationDocument,
            self::ProjectProductDescription => DocumentCategory::Initiation,

            self::ProjectPlan,
            self::StagePlan,
            self::TeamPlan,
            self::ExceptionPlan,
            self::WorkPackage               => DocumentCategory::Planning,

            self::HighlightReport,
            self::CheckpointReport,
            self::EndStageReport,
            self::EndProjectReport,
            self::ExceptionReport,
            self::LessonsReport             => DocumentCategory::Reporting,

            self::RiskRegister,
            self::IssueRegister,
            self::QualityRegister,
            self::ChangeLog,
            self::LessonsLog,
            self::DailyLog                  => DocumentCategory::Register,

            self::RequirementsSpecification,
            self::TestSpecification,
            self::TestSessionPlan,
            self::TestExecutionReport,
            self::TraceabilityMatrix        => DocumentCategory::Qa,

            self::MeetingAgenda,
            self::MeetingMinutes            => DocumentCategory::Meeting,

            self::General                   => DocumentCategory::General,
        };
    }
}
