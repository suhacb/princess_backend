<?php

use App\Http\Controllers\ChangeLogController;
use App\Http\Controllers\DailyLogController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\IssueLogController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
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
});
