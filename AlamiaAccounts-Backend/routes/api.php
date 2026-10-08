<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ManualController;
use App\Http\Controllers\Api\V1\SalesIntegrationController;

/*
|--------------------------------------------------------------------------
| Application API Routes
|--------------------------------------------------------------------------
|
| Direct API routes for Alamia Accounts Backend.
|
*/

// Accountant Help & User Manual Portal API
Route::get('/manual', [ManualController::class, 'index']);

// Front-Office & Sales POS Integration API (v1)
Route::post('/v1/sales', [SalesIntegrationController::class, 'store']);
Route::get('/v1/sales', [SalesIntegrationController::class, 'index']);
Route::get('/v1/sales/reconcile-shift', [SalesIntegrationController::class, 'reconcileShift']);
Route::get('/v1/sales/{id}', [SalesIntegrationController::class, 'show'])->whereNumber('id');
Route::post('/v1/sales/{id}/approve', [SalesIntegrationController::class, 'approve'])->whereNumber('id');
Route::get('/v1/receipts/{id}/print', [SalesIntegrationController::class, 'printReceipt'])->whereNumber('id');
