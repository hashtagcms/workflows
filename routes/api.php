<?php

use Illuminate\Support\Facades\Route;
use HashtagCms\Workflows\Http\Controllers\Api\WorkflowExecutionController;

// Base prefix for all HashtagCms API routes. Configurable via HASHTAGCMS_API_PREFIX.
$prefix = config('hashtagcmsapi.route_prefix', 'api/hashtagcms');

Route::middleware(['api'])->prefix($prefix . '/public/workflows/v1')->group(function () {
    Route::post('/execute', [WorkflowExecutionController::class, 'execute']);
    Route::get('/health', [WorkflowExecutionController::class, 'health']);
    Route::get('/directives', [WorkflowExecutionController::class, 'directives']);
});
