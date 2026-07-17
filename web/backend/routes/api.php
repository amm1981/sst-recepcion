<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MailSettingsController;
use App\Http\Controllers\Api\MedicalDocumentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\WorkerController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/medical-documents/files/{file}/preview', [MedicalDocumentController::class, 'previewFile']);
Route::get('/medical-documents/files/{file}/download-signed', [MedicalDocumentController::class, 'signedDownloadFile'])
    ->middleware('signed')
    ->name('medical-documents.files.download-signed');
Route::get('/medical-documents/files/{file}/preview-signed', [MedicalDocumentController::class, 'signedPreviewFile'])
    ->middleware('signed')
    ->name('medical-documents.files.preview-signed');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);

    Route::get('/medical-documents/counts', [MedicalDocumentController::class, 'counts']);
    Route::get('/medical-documents/{medicalDocument}/history', [MedicalDocumentController::class, 'history']);
    Route::patch('/medical-documents/{medicalDocument}/observation', [MedicalDocumentController::class, 'updateObservation']);
    Route::post('/medical-documents/{medicalDocument}/status', [MedicalDocumentController::class, 'changeStatus'])->middleware('permission:documents.updateStatus');
    Route::get('/medical-documents/files/{file}/download', [MedicalDocumentController::class, 'downloadFile']);
    Route::apiResource('medical-documents', MedicalDocumentController::class)
        ->parameters(['medical-documents' => 'medicalDocument']);

    Route::get('/workers/search/{dni}', [WorkerController::class, 'searchByDni']);
    Route::get('/workers-registered-documents', [WorkerController::class, 'registeredWithDocuments'])->middleware('permission:documents.view');
    Route::get('/workers/import-template', [WorkerController::class, 'importTemplate'])->middleware('permission:workers.manage');
    Route::post('/workers/import-excel', [WorkerController::class, 'importExcel'])->middleware('permission:workers.manage');
    Route::apiResource('workers', WorkerController::class)->except(['show']);

    Route::get('/reports/registrars', [ReportController::class, 'registrars'])->middleware('permission:reports.view');
    Route::get('/reports/summary', [ReportController::class, 'summary'])->middleware('permission:reports.view');
    Route::get('/reports/export/excel', [ReportController::class, 'exportExcel'])->middleware('permission:reports.view');
    Route::get('/reports/export/detail-excel', [ReportController::class, 'exportDetailExcel'])->middleware('permission:reports.view');
    Route::get('/reports/export/pdf', [ReportController::class, 'exportPdf'])->middleware('permission:reports.view');

    Route::prefix('admin')->middleware('permission:admin.manage')->group(function () {
        Route::post('/workers/sync-employee-flow', [\App\Http\Controllers\Api\WorkerSyncController::class, 'trigger']);
        Route::get('/workers/sync-employee-flow/latest', [\App\Http\Controllers\Api\WorkerSyncController::class, 'latest']);
        Route::get('/mail-settings', [MailSettingsController::class, 'show']);
        Route::put('/mail-settings', [MailSettingsController::class, 'update']);
        Route::post('/mail-settings/test', [MailSettingsController::class, 'sendTest']);
        
        Route::get('/audit-logs', [\App\Http\Controllers\Api\AuditLogController::class, 'index']);
        Route::get('/{resource}', [AdminController::class, 'index']);
        Route::post('/{resource}', [AdminController::class, 'store']);
        Route::put('/{resource}/{id}', [AdminController::class, 'update']);
        Route::delete('/{resource}/{id}', [AdminController::class, 'destroy']);
    });

    Route::prefix('sync')->group(function () {
        Route::get('/workers', [SyncController::class, 'workers']);
        Route::get('/catalogs', [SyncController::class, 'catalogs']);
        Route::get('/permissions', [SyncController::class, 'permissions']);
        Route::post('/documents', [SyncController::class, 'uploadDocument'])->middleware('permission:documents.create');
        Route::post('/logs', [SyncController::class, 'log']);
    });
});
