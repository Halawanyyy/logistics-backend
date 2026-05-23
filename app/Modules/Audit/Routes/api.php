<?php

use App\Modules\Audit\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit_logs.view');
    Route::get('/audit-logs/timeline', [AuditLogController::class, 'timeline'])->middleware('permission:audit_logs.timeline');
});
