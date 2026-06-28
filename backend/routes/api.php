<?php

use App\Http\Controllers\AiConfigController;
use App\Http\Controllers\MigrationBundleController;
use App\Http\Controllers\MigrationPreviewController;
use App\Http\Controllers\MigrationProjectController;
use App\Http\Controllers\MigrationReportController;
use App\Http\Controllers\MigrationWorkflowController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::apiResource('projects', MigrationProjectController::class);

    Route::prefix('projects/{project}')->group(function () {
        Route::post('/prepare', [MigrationWorkflowController::class, 'prepare']);
        Route::post('/validate', [MigrationWorkflowController::class, 'validate']);
        Route::get('/preview', [MigrationWorkflowController::class, 'preview']);
        Route::post('/migrate', [MigrationWorkflowController::class, 'migrate']);
        Route::post('/rollback', [MigrationWorkflowController::class, 'rollback']);
        Route::get('/report', [MigrationWorkflowController::class, 'report']);

        Route::prefix('bundle')->group(function () {
            Route::get('/preview', [MigrationBundleController::class, 'preview']);
            Route::post('/export', [MigrationBundleController::class, 'export']);
            Route::get('/download', [MigrationBundleController::class, 'download']);
        });

        Route::prefix('ai-config')->group(function () {
            Route::get('/', [AiConfigController::class, 'index']);
            Route::post('/', [AiConfigController::class, 'store']);
            Route::get('/{config}', [AiConfigController::class, 'show']);
            Route::put('/{config}', [AiConfigController::class, 'update']);
            Route::delete('/{config}', [AiConfigController::class, 'destroy']);
        });

        Route::post('/ai/analyze', [AiConfigController::class, 'analyze']);

        Route::prefix('preview')->group(function () {
            Route::get('/', [MigrationPreviewController::class, 'index']);
            Route::post('/test-connection', [MigrationPreviewController::class, 'testConnection']);
        });

        Route::prefix('reports')->group(function () {
            Route::get('/', [MigrationReportController::class, 'index']);
            Route::get('/latest', [MigrationReportController::class, 'latest']);
            Route::get('/{report}', [MigrationReportController::class, 'show']);
        });

        Route::prefix('inventory')->group(function () {
            Route::post('/generate', [App\Http\Controllers\MigrationInventoryController::class, 'generate']);
        });
    });
});
