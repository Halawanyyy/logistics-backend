<?php

use App\Modules\Attachment\Controllers\AttachmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('attachments')->group(function () {
        Route::get('/', [AttachmentController::class, 'index'])->middleware('permission:attachments.view')->name('attachments.index');
        Route::post('/', [AttachmentController::class, 'store'])->middleware('permission:attachments.upload')->name('attachments.store');
        Route::get('/{attachment}', [AttachmentController::class, 'show'])->middleware('permission:attachments.view')->name('attachments.show');
        Route::get('/{attachment}/download', [AttachmentController::class, 'download'])->middleware('permission:attachments.download')->name('attachments.download');
        Route::delete('/{attachment}', [AttachmentController::class, 'destroy'])->middleware('permission:attachments.delete')->name('attachments.destroy');
    });
});
