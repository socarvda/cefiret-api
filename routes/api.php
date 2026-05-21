<?php

use App\Http\Controllers\API\AuthApiController;
use App\Http\Controllers\API\CitaApiController;
use App\Http\Controllers\API\EmailConfirmationApiController;
use App\Http\Controllers\API\ExpedienteApiController;
use App\Http\Controllers\API\ProgresoApiController;
use App\Http\Controllers\API\ResetPasswordApiController;
use App\Http\Controllers\API\RutinaApiController;
use App\Http\Controllers\API\UsuarioApiController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn() => response()->json(['success' => true, 'message' => 'CEFIRET API OK']));

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthApiController::class, 'login']);
    Route::get('/me', [AuthApiController::class, 'me']);
    Route::post('/logout', [AuthApiController::class, 'logout']);
    Route::post('/forgot-password', [ResetPasswordApiController::class, 'sendResetLinkEmail']);
    Route::post('/reset-password', [ResetPasswordApiController::class, 'resetPassword']);
    Route::get('/confirm-email/{token}', [EmailConfirmationApiController::class, 'confirm']);
});

Route::apiResource('usuarios', UsuarioApiController::class)->except(['destroy']);

Route::prefix('expedientes')->group(function () {
    Route::get('/pacientes', [ExpedienteApiController::class, 'pacientes']);
    Route::get('/pacientes/{idUsuario}', [ExpedienteApiController::class, 'show']);
    Route::post('/pacientes/{idUsuario}', [ExpedienteApiController::class, 'store']);
    Route::get('/pacientes/{idUsuario}/citas', [ExpedienteApiController::class, 'citas']);
});

Route::prefix('rutinas')->group(function () {
    Route::get('/', [RutinaApiController::class, 'index']);
    Route::post('/', [RutinaApiController::class, 'store']);
    Route::get('/{id}', [RutinaApiController::class, 'show']);
    Route::put('/{id}', [RutinaApiController::class, 'update']);
    Route::delete('/{id}', [RutinaApiController::class, 'destroy']);
    Route::post('/asignar-existente', [RutinaApiController::class, 'asignarExistente']);
});

Route::prefix('citas')->group(function () {
    Route::get('/', [CitaApiController::class, 'index']);
    Route::get('/opciones', [CitaApiController::class, 'opciones']);
    Route::get('/events', [CitaApiController::class, 'events']);
    Route::get('/disponibilidad', [CitaApiController::class, 'disponibilidad']);
    Route::post('/', [CitaApiController::class, 'store']);
    Route::get('/{id}', [CitaApiController::class, 'show']);
    Route::put('/{id}', [CitaApiController::class, 'update']);
    Route::post('/{id}/cancelar', [CitaApiController::class, 'cancelar']);
    Route::delete('/{id}', [CitaApiController::class, 'destroy']);
});

Route::prefix('progreso')->group(function () {
    Route::get('/pacientes', [ProgresoApiController::class, 'pacientes']);
    Route::get('/pacientes/{idPaciente}', [ProgresoApiController::class, 'show']);
    Route::get('/rutinas/{idRutina}', [ProgresoApiController::class, 'byRutina']);
    Route::post('/', [ProgresoApiController::class, 'store']);
});
