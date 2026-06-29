<?php

use App\Http\Controllers\AcceptanceCriterionController;
use App\Http\Controllers\DocumentTemplateController;
use App\Http\Controllers\OnlyOfficeCallbackController;
use App\Http\Controllers\OnlyOfficeEditorConfigController;
use App\Http\Controllers\E2eController;
use App\Http\Controllers\CheckpointReportController;
use App\Http\Controllers\ExceptionReportController;
use App\Http\Controllers\HighlightReportController;
use App\Http\Controllers\PeriodSummaryController;
use App\Http\Controllers\VarianceController;
use App\Http\Controllers\TestCaseController;
use App\Http\Controllers\TestScenarioController;
use App\Http\Controllers\TestSessionController;
use App\Http\Controllers\TestSessionPlanController;
use App\Http\Controllers\TraceabilityController;
use App\Http\Controllers\ChangeLogController;
use App\Http\Controllers\DailyLogController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\IssueLogController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductFlowController;
use App\Http\Controllers\DocumentVersionController;
use App\Http\Controllers\DocumentLinkController;
use App\Http\Controllers\QaDocumentController;
use App\Http\Controllers\RequirementController;
use App\Http\Controllers\WorkPackageController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\ProjectProductDescriptionController;
use App\Http\Controllers\QualityRegisterController;
use App\Http\Controllers\RiskController;
use App\Http\Controllers\StageBoundaryController;
use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\MeetingActionItemController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('health', [HealthController::class, 'check'])->name('health');
Route::get('health/auth', [HealthController::class, 'authBackend'])->name('health.auth');

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [LoginController::class, 'login'])->name('login');
    Route::get('validate-access-token', [LoginController::class, 'validateAccessToken'])->name('validate-access-token');
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');
});

Route::middleware('e2e.only')->post('e2e/reset', [E2eController::class, 'reset'])->name('e2e.reset');

Route::post('onlyoffice/callback/{key}', OnlyOfficeCallbackController::class)
    ->name('onlyoffice.callback');

Route::middleware('verify.frontend')->scopeBindings()->group(function () {
    Route::apiResource('projects', ProjectController::class);
    Route::patch('projects/{project}/current-stage', [ProjectController::class, 'setCurrentStage'])
        ->name('projects.set-current-stage');

    Route::apiResource('projects.members', ProjectMemberController::class)->except('show');

    Route::apiResource('projects.stages', StageController::class);
    Route::patch('projects/{project}/stages/{stage}/transition', [StageController::class, 'transition'])
        ->name('projects.stages.transition');

    Route::apiResource('projects.stages.boundaries', StageBoundaryController::class);
    Route::patch('projects/{project}/stages/{stage}/boundaries/{boundary}/submit', [StageBoundaryController::class, 'submit'])
        ->name('projects.stages.boundaries.submit');
    Route::patch('projects/{project}/stages/{stage}/boundaries/{boundary}/approve', [StageBoundaryController::class, 'approve'])
        ->name('projects.stages.boundaries.approve');
    Route::patch('projects/{project}/stages/{stage}/boundaries/{boundary}/reject', [StageBoundaryController::class, 'reject'])
        ->name('projects.stages.boundaries.reject');

    Route::apiResource('projects.daily-log', DailyLogController::class)->parameters(['daily-log' => 'dailyLogEntry']);

    Route::apiResource('projects.issues', IssueLogController::class);
    Route::post('projects/{project}/issues/{issue}/escalate', [IssueLogController::class, 'escalate'])
        ->name('projects.issues.escalate');
    Route::post('projects/{project}/issues/{issue}/resolve', [IssueLogController::class, 'resolve'])
        ->name('projects.issues.resolve');

    Route::apiResource('projects.risks', RiskController::class);

    Route::apiResource('projects.changes', ChangeLogController::class);
    Route::patch('projects/{project}/changes/{change}/approve', [ChangeLogController::class, 'approve'])
        ->name('projects.changes.approve');
    Route::patch('projects/{project}/changes/{change}/reject', [ChangeLogController::class, 'reject'])
        ->name('projects.changes.reject');

    Route::apiResource('projects.quality-register', QualityRegisterController::class)->parameters(['quality-register' => 'qualityRegisterEntry']);

    Route::apiResource('projects.lessons', LessonController::class);

    Route::get('projects/{project}/product-description', [ProjectProductDescriptionController::class, 'show'])
        ->name('projects.product-description.show');
    Route::post('projects/{project}/product-description', [ProjectProductDescriptionController::class, 'store'])
        ->name('projects.product-description.store');
    Route::put('projects/{project}/product-description', [ProjectProductDescriptionController::class, 'update'])
        ->name('projects.product-description.update');
    Route::delete('projects/{project}/product-description', [ProjectProductDescriptionController::class, 'destroy'])
        ->name('projects.product-description.destroy');

    Route::get('projects/{project}/products/tree', [ProductController::class, 'tree'])
        ->name('projects.products.tree');
    Route::apiResource('projects.products', ProductController::class);
    Route::post('projects/{project}/products/{product}/baseline', [ProductController::class, 'baseline'])
        ->name('projects.products.baseline');

    Route::get('projects/{project}/product-flow', [ProductFlowController::class, 'show'])
        ->name('projects.product-flow.show');
    Route::post('projects/{project}/product-flow', [ProductFlowController::class, 'store'])
        ->name('projects.product-flow.store');
    Route::delete('projects/{project}/product-flow/{dependency}', [ProductFlowController::class, 'destroy'])
        ->name('projects.product-flow.destroy')
        ->withoutScopedBindings();

    Route::apiResource('projects.plans', PlanController::class);
    Route::post('projects/{project}/plans/{plan}/approve', [PlanController::class, 'approve'])
        ->name('projects.plans.approve');

    Route::apiResource('projects.requirements', RequirementController::class);
    Route::post('projects/{project}/requirements/{requirement}/review', [RequirementController::class, 'review'])
        ->name('projects.requirements.review');
    Route::post('projects/{project}/requirements/{requirement}/approve', [RequirementController::class, 'approve'])
        ->name('projects.requirements.approve');
    Route::post('projects/{project}/requirements/{requirement}/reject', [RequirementController::class, 'reject'])
        ->name('projects.requirements.reject');
    Route::post('projects/{project}/requirements/{requirement}/defer', [RequirementController::class, 'defer'])
        ->name('projects.requirements.defer');

    Route::apiResource('projects.acceptance-criteria', AcceptanceCriterionController::class)
        ->parameters(['acceptance-criteria' => 'acceptanceCriterion']);
    Route::post('projects/{project}/acceptance-criteria/{acceptanceCriterion}/approve', [AcceptanceCriterionController::class, 'approve'])
        ->name('projects.acceptance-criteria.approve');

    Route::apiResource('projects.qa-documents', QaDocumentController::class)
        ->parameters(['qa-documents' => 'qaDocument']);
    Route::post('projects/{project}/qa-documents/{qaDocument}/send-for-review', [QaDocumentController::class, 'sendForReview'])
        ->name('projects.qa-documents.send-for-review');
    Route::post('projects/{project}/qa-documents/{qaDocument}/reject', [QaDocumentController::class, 'reject'])
        ->name('projects.qa-documents.reject');
    Route::post('projects/{project}/qa-documents/{qaDocument}/confirm', [QaDocumentController::class, 'confirm'])
        ->name('projects.qa-documents.confirm');
    Route::get('projects/{project}/qa-documents/{qaDocument}/versions', [DocumentVersionController::class, 'index'])
        ->name('projects.qa-documents.versions.index');
    Route::post('projects/{project}/qa-documents/{qaDocument}/versions/{version}/revert', [DocumentVersionController::class, 'revert'])
        ->name('projects.qa-documents.versions.revert');
    Route::post('projects/{project}/qa-documents/{qaDocument}/upload', [DocumentVersionController::class, 'upload'])
        ->name('projects.qa-documents.upload');
    Route::get('projects/{project}/qa-documents/{qaDocument}/download', [DocumentVersionController::class, 'download'])
        ->name('projects.qa-documents.download');
    Route::get('projects/{project}/qa-documents/{qaDocument}/editor-config', OnlyOfficeEditorConfigController::class)
        ->name('projects.qa-documents.editor-config');
    Route::post('projects/{project}/qa-documents/{qaDocument}/link', [DocumentLinkController::class, 'link'])
        ->name('projects.qa-documents.link');
    Route::delete('projects/{project}/qa-documents/{qaDocument}/link', [DocumentLinkController::class, 'unlink'])
        ->name('projects.qa-documents.unlink');

    Route::apiResource('projects.test-scenarios', TestScenarioController::class)
        ->parameters(['test-scenarios' => 'testScenario']);
    Route::post('projects/{project}/test-scenarios/{testScenario}/ready', [TestScenarioController::class, 'ready'])
        ->name('projects.test-scenarios.ready');
    Route::post('projects/{project}/test-scenarios/{testScenario}/obsolete', [TestScenarioController::class, 'obsolete'])
        ->name('projects.test-scenarios.obsolete');
    Route::post('projects/{project}/test-scenarios/{testScenario}/reopen', [TestScenarioController::class, 'reopen'])
        ->name('projects.test-scenarios.reopen');
    Route::post('projects/{project}/test-scenarios/{testScenario}/mark-testable', [TestScenarioController::class, 'markTestable'])
        ->name('projects.test-scenarios.mark-testable');
    Route::post('projects/{project}/test-scenarios/{testScenario}/mark-not-testable', [TestScenarioController::class, 'markNotTestable'])
        ->name('projects.test-scenarios.mark-not-testable');
    Route::get('projects/{project}/test-scenarios/{testScenario}/document', [TestScenarioController::class, 'document'])
        ->name('projects.test-scenarios.document');

    Route::apiResource('projects.test-scenarios.test-cases', TestCaseController::class)
        ->parameters(['test-cases' => 'testCase']);

    Route::apiResource('projects.test-session-plans', TestSessionPlanController::class)
        ->parameters(['test-session-plans' => 'testSessionPlan']);
    Route::post('projects/{project}/test-session-plans/{testSessionPlan}/activate', [TestSessionPlanController::class, 'activate'])
        ->name('projects.test-session-plans.activate');
    Route::post('projects/{project}/test-session-plans/{testSessionPlan}/complete', [TestSessionPlanController::class, 'complete'])
        ->name('projects.test-session-plans.complete');
    Route::post('projects/{project}/test-session-plans/{testSessionPlan}/cancel', [TestSessionPlanController::class, 'cancel'])
        ->name('projects.test-session-plans.cancel');
    Route::get('projects/{project}/test-session-plans/{testSessionPlan}/document', [TestSessionPlanController::class, 'document'])
        ->name('projects.test-session-plans.document');

    Route::apiResource('projects.test-sessions', TestSessionController::class)
        ->parameters(['test-sessions' => 'testSession']);
    Route::post('projects/{project}/test-sessions/{testSession}/start', [TestSessionController::class, 'start'])
        ->name('projects.test-sessions.start');
    Route::post('projects/{project}/test-sessions/{testSession}/complete', [TestSessionController::class, 'complete'])
        ->name('projects.test-sessions.complete');
    Route::post('projects/{project}/test-sessions/{testSession}/cancel', [TestSessionController::class, 'cancel'])
        ->name('projects.test-sessions.cancel');
    Route::put('projects/{project}/test-sessions/{testSession}/results/{testScenario}', [TestSessionController::class, 'updateResult'])
        ->name('projects.test-sessions.results.update')
        ->withoutScopedBindings();
    Route::get('projects/{project}/test-sessions/{testSession}/report', [TestSessionController::class, 'report'])
        ->name('projects.test-sessions.report');

    Route::get('projects/{project}/traceability', [TraceabilityController::class, 'index'])
        ->name('projects.traceability');

    Route::apiResource('projects.checkpoint-reports', CheckpointReportController::class)
        ->parameters(['checkpoint-reports' => 'checkpointReport']);
    Route::post('projects/{project}/checkpoint-reports/{checkpointReport}/submit', [CheckpointReportController::class, 'submit'])
        ->name('projects.checkpoint-reports.submit');
    Route::post('projects/{project}/checkpoint-reports/{checkpointReport}/acknowledge', [CheckpointReportController::class, 'acknowledge'])
        ->name('projects.checkpoint-reports.acknowledge');

    Route::get('projects/{project}/period-summary', [PeriodSummaryController::class, 'index'])
        ->name('projects.period-summary');

    Route::apiResource('projects.highlight-reports', HighlightReportController::class)
        ->parameters(['highlight-reports' => 'highlightReport']);
    Route::post('projects/{project}/highlight-reports/{highlightReport}/submit', [HighlightReportController::class, 'submit'])
        ->name('projects.highlight-reports.submit');
    Route::post('projects/{project}/highlight-reports/{highlightReport}/approve', [HighlightReportController::class, 'approve'])
        ->name('projects.highlight-reports.approve');

    Route::get('projects/{project}/variance', [VarianceController::class, 'index'])
        ->name('projects.variance');
    Route::get('projects/{project}/stages/{stage}/variance', [VarianceController::class, 'show'])
        ->name('projects.stages.variance');

    Route::apiResource('projects.exception-reports', ExceptionReportController::class)
        ->parameters(['exception-reports' => 'exceptionReport']);
    Route::post('projects/{project}/exception-reports/{exceptionReport}/submit', [ExceptionReportController::class, 'submit'])
        ->name('projects.exception-reports.submit');
    Route::post('projects/{project}/exception-reports/{exceptionReport}/close', [ExceptionReportController::class, 'close'])
        ->name('projects.exception-reports.close');

    Route::apiResource('projects.work-packages', WorkPackageController::class);
    Route::post('projects/{project}/work-packages/{workPackage}/authorize', [WorkPackageController::class, 'issue'])
        ->name('projects.work-packages.authorize');
    Route::post('projects/{project}/work-packages/{workPackage}/accept', [WorkPackageController::class, 'accept'])
        ->name('projects.work-packages.accept');
    Route::post('projects/{project}/work-packages/{workPackage}/complete', [WorkPackageController::class, 'complete'])
        ->name('projects.work-packages.complete');
    Route::post('projects/{project}/work-packages/{workPackage}/cancel', [WorkPackageController::class, 'cancel'])
        ->name('projects.work-packages.cancel');
    Route::post('projects/{project}/work-packages/{workPackage}/raise-exception', [ExceptionReportController::class, 'raiseException'])
        ->name('projects.work-packages.raise-exception');

    Route::apiResource('projects.tasks', TaskController::class);
    Route::get('projects/{project}/tasks/{task}/history', [TaskController::class, 'history'])
        ->name('projects.tasks.history');

    Route::get('projects/{project}/templates', [DocumentTemplateController::class, 'index'])
        ->name('projects.templates.index');
    Route::post('projects/{project}/templates', [DocumentTemplateController::class, 'store'])
        ->name('projects.templates.store');
    Route::put('projects/{project}/templates/{template}', [DocumentTemplateController::class, 'update'])
        ->name('projects.templates.update');
    Route::delete('projects/{project}/templates/{template}', [DocumentTemplateController::class, 'destroy'])
        ->name('projects.templates.destroy');
    Route::post('projects/{project}/templates/{template}/upload', [DocumentTemplateController::class, 'upload'])
        ->name('projects.templates.upload');

    Route::get('projects/{project}/audit-trail', [AuditTrailController::class, 'index'])
        ->name('projects.audit-trail');

    Route::apiResource('projects.meetings', MeetingController::class);
    Route::post('projects/{project}/meetings/{meeting}/action-items', [MeetingActionItemController::class, 'store'])
        ->name('projects.meetings.action-items.store');
    Route::patch('projects/{project}/meetings/{meeting}/action-items/{actionItem}', [MeetingActionItemController::class, 'update'])
        ->name('projects.meetings.action-items.update');
    Route::delete('projects/{project}/meetings/{meeting}/action-items/{actionItem}', [MeetingActionItemController::class, 'destroy'])
        ->name('projects.meetings.action-items.destroy');
});
