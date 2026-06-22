<?php

use App\Http\Controllers\AcceptanceCriterionController;
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
use App\Http\Controllers\QaDocumentController;
use App\Http\Controllers\RequirementController;
use App\Http\Controllers\WorkPackageController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\ProjectProductDescriptionController;
use App\Http\Controllers\QualityRegisterController;
use App\Http\Controllers\RiskController;
use App\Http\Controllers\StageBoundaryController;
use App\Http\Controllers\StageController;
use Illuminate\Support\Facades\Route;

Route::get('health', [HealthController::class, 'check'])->name('health');
Route::get('health/auth', [HealthController::class, 'authBackend'])->name('health.auth');

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [LoginController::class, 'login'])->name('login');
    Route::get('validate-access-token', [LoginController::class, 'validateAccessToken'])->name('validate-access-token');
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');
});

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

    Route::apiResource('projects.work-packages', WorkPackageController::class);
    Route::post('projects/{project}/work-packages/{workPackage}/authorize', [WorkPackageController::class, 'issue'])
        ->name('projects.work-packages.authorize');
    Route::post('projects/{project}/work-packages/{workPackage}/accept', [WorkPackageController::class, 'accept'])
        ->name('projects.work-packages.accept');
    Route::post('projects/{project}/work-packages/{workPackage}/complete', [WorkPackageController::class, 'complete'])
        ->name('projects.work-packages.complete');
    Route::post('projects/{project}/work-packages/{workPackage}/cancel', [WorkPackageController::class, 'cancel'])
        ->name('projects.work-packages.cancel');
});
