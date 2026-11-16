<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Webstat\Analytics\Http\Controllers\CollectController;

// Deliberately outside the "web" group: no session, cookies or CSRF involved.
Route::post('c', CollectController::class)
    ->middleware('throttle:analytics-collect')
    ->name('analytics.collect');
