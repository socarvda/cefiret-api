<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmailConfirmationController;
use App\Http\Controllers\GoogleCalendarController;

Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'CEFIRET backend API funcionando.'
    ]);
});

Route::get('/email/confirm/{token}', [EmailConfirmationController::class, 'confirm'])
    ->name('email.confirm');

Route::get('/google/calendar/connect', [GoogleCalendarController::class, 'redirect'])
    ->name('google.calendar.connect');

Route::get('/auth/callback', [GoogleCalendarController::class, 'callback'])
    ->name('google.calendar.callback');

Route::get('/google/calendar/disconnect', [GoogleCalendarController::class, 'disconnect'])
    ->name('google.calendar.disconnect');

Route::get('/google/calendar/status', [GoogleCalendarController::class, 'status'])
    ->name('google.calendar.status');

Route::get('/google/calendar/disponibilidad', [GoogleCalendarController::class, 'disponibilidad'])
    ->name('google.calendar.disponibilidad');