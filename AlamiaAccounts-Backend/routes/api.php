<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Application API Routes
|--------------------------------------------------------------------------
|
| Host-application level API routes.
| Core Alamia Accounts package routes are registered automatically
| via AlamiaSoft\AlamiaAccounts\AlamiaAccountsServiceProvider.
|
*/

Route::get('/health', function () {
    return response()->json(['status' => 'healthy', 'service' => 'alamia-accounts-host']);
});
