<?php

namespace App\Enums;

enum QaDocumentType: string
{
    case RequirementsSpecification = 'requirements_specification';
    case TestSpecification         = 'test_specification';
    case TestSessionPlan           = 'test_session_plan';
    case TestExecutionReport       = 'test_execution_report';
    case TraceabilityMatrix        = 'traceability_matrix';
}
