<?php

use App\Http\Controllers\MockProviderController;
use Illuminate\Support\Facades\Route;

Route::prefix('mock')->group(function () {
    Route::get('provider-a', [MockProviderController::class, 'a']);
    Route::get('provider-b', [MockProviderController::class, 'b']);
    Route::get('provider-c', [MockProviderController::class, 'c']);
});