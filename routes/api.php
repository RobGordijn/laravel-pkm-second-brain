<?php

use App\Http\Controllers\Api\CaptureController;
use App\Http\Middleware\EnsureCaptureToken;
use Illuminate\Support\Facades\Route;

Route::post('/capture', CaptureController::class)
    ->middleware([EnsureCaptureToken::class, 'throttle:60,1'])
    ->name('api.capture');
