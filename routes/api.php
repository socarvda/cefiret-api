<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthApiController;
use App\Http\Controllers\API\DashboardApiController;
use App\Http\Controllers\API\UsuarioApiController;
use App\Http\Controllers\API\ExpedienteApiController;
use App\Http\Controllers\API\RutinaApiController;
use App\Http\Controllers\API\CitaApiController;
use App\Http\Controllers\API\ProgresoApiController;
use App\Http\Controllers\API\ResetPasswordApiController;
use App\Http\Controllers\API\MobileApiController;
use App\Http\Controllers\API\NotificacionApiController;
use App\Http\Controllers\API\PagoApiController;
use App\Http\Controllers\API\HistorialVisitaApiController;

/*
|--------------------------------------------------------------------------
| Rutas públicas
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthApiController::class, 'login']);

Route::post('/password/email', [ResetPasswordApiController::class, 'sendResetLinkEmail']);
Route::put('/password/update', [ResetPasswordApiController::class, 'resetPassword']);

/*
|--------------------------------------------------------------------------
| Rutas protegidas por token
|--------------------------------------------------------------------------
*/

Route::middleware('api.token')->group(function () {
    Route::post('/logout', [AuthApiController::class, 'logout']);

    Route::get('/me', [AuthApiController::class, 'me']);
    Route::get('/dashboard', [DashboardApiController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | Notificaciones
    |--------------------------------------------------------------------------
    */

    Route::get('/notificaciones', [NotificacionApiController::class, 'index']);
    Route::get('/notificaciones/no-leidas', [NotificacionApiController::class, 'noLeidas']);
    Route::put('/notificaciones/{id}/leida', [NotificacionApiController::class, 'marcarLeida']);
    Route::put('/notificaciones/marcar-todas-leidas', [NotificacionApiController::class, 'marcarTodasLeidas']);

    /*
    |--------------------------------------------------------------------------
    | Rutas para pacientes / app móvil / vistas de paciente
    |--------------------------------------------------------------------------
    | Paciente: solo puede consultar su propio ID.
    | Admin/fisio: pueden consultar cualquier paciente.
    |
    | IMPORTANTE:
    | /paciente/{id}/videos  = app móvil, se deja como estaba.
    | /paciente/{id}/rutinas = sistema web, expediente y vista Mis rutinas.
    |--------------------------------------------------------------------------
    */

    Route::get('/paciente/{id}/videos', [MobileApiController::class, 'videosPaciente']);
    Route::get('/paciente/{id}/rutinas', [MobileApiController::class, 'rutinasPaciente']);
    Route::get('/paciente/{id}/citas', [MobileApiController::class, 'citasPaciente']);
    Route::get('/pagos/{id}', [MobileApiController::class, 'pagosPaciente']);
    Route::get('/paciente/{id}/notificaciones', [NotificacionApiController::class, 'paciente']);

    Route::get('/progreso/paciente/{id}', [ProgresoApiController::class, 'show']);
    Route::get('/progreso/paciente/{id}/reporte', [ProgresoApiController::class, 'report']);

    /*
    |--------------------------------------------------------------------------
    | Progreso desde app móvil y sistema web
    |--------------------------------------------------------------------------
    | Este POST debe estar fuera de role:1,2 porque la app es para pacientes.
    | El controlador se encarga de validar que el paciente solo registre
    | progreso de sus propias rutinas.
    |--------------------------------------------------------------------------
    */

    Route::post('/progreso', [ProgresoApiController::class, 'store']);

    /*
    |--------------------------------------------------------------------------
    | Pagos para paciente
    |--------------------------------------------------------------------------
    */

    Route::get('/paciente/{id}/pagos', [PagoApiController::class, 'paciente']);

    /*
    |--------------------------------------------------------------------------
    | Historial de visitas
    |--------------------------------------------------------------------------
    | El paciente puede consultar su propio historial.
    | Admin/fisio pueden consultar cualquier historial.
    |--------------------------------------------------------------------------
    */

    Route::get('/paciente/{id}/historial-visitas', [HistorialVisitaApiController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | Stripe Checkout
    |--------------------------------------------------------------------------
    */

    Route::post('/stripe/pagos/{idPago}/checkout', [PagoApiController::class, 'crearCheckout']);
    Route::post('/stripe/confirmar', [PagoApiController::class, 'confirmarStripe']);

    /*
    |--------------------------------------------------------------------------
    | Administrador y fisioterapeuta
    |--------------------------------------------------------------------------
    | 1 = Administrador
    | 2 = Fisioterapeuta
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:1,2')->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Usuarios
        |--------------------------------------------------------------------------
        */

        Route::get('/usuarios', [UsuarioApiController::class, 'index']);
        Route::post('/usuarios', [UsuarioApiController::class, 'store']);
        Route::get('/usuarios/{id}', [UsuarioApiController::class, 'show']);
        Route::put('/usuarios/{id}', [UsuarioApiController::class, 'update']);

        /*
        |--------------------------------------------------------------------------
        | Expedientes
        |--------------------------------------------------------------------------
        */

        Route::get('/expedientes/pacientes', [ExpedienteApiController::class, 'pacientes']);
        Route::get('/expedientes/pacientes/{id}', [ExpedienteApiController::class, 'show']);
        Route::post('/expedientes/pacientes/{id}', [ExpedienteApiController::class, 'store']);
        Route::put('/expedientes/pacientes/{id}', [ExpedienteApiController::class, 'update']);
        Route::get('/expedientes/pacientes/{id}/citas', [ExpedienteApiController::class, 'citas']);

        /*
        |--------------------------------------------------------------------------
        | Rutinas
        |--------------------------------------------------------------------------
        */

        Route::get('/rutinas', [RutinaApiController::class, 'index']);
        Route::post('/rutinas', [RutinaApiController::class, 'store']);
        Route::get('/rutinas/{id}', [RutinaApiController::class, 'show']);
        Route::put('/rutinas/{id}', [RutinaApiController::class, 'update']);
        Route::delete('/rutinas/{id}', [RutinaApiController::class, 'destroy']);

        /*
        |--------------------------------------------------------------------------
        | Citas
        |--------------------------------------------------------------------------
        | Importante: las rutas especiales van antes de /citas/{id}
        |--------------------------------------------------------------------------
        */

        Route::get('/citas', [CitaApiController::class, 'index']);
        Route::post('/citas', [CitaApiController::class, 'store']);
        Route::get('/citas/events', [CitaApiController::class, 'events']);
        Route::get('/citas/disponibilidad', [CitaApiController::class, 'disponibilidad']);
        Route::get('/citas/{id}', [CitaApiController::class, 'show']);
        Route::put('/citas/{id}', [CitaApiController::class, 'update']);
        Route::delete('/citas/{id}', [CitaApiController::class, 'destroy']);
        Route::post('/citas/{id}/cancelar', [CitaApiController::class, 'cancelar']);

        /*
        |--------------------------------------------------------------------------
        | Progreso
        |--------------------------------------------------------------------------
        | GET queda solo para admin/fisio.
        | POST /progreso está arriba para que también funcione la app del paciente.
        |--------------------------------------------------------------------------
        */

        Route::get('/progreso', [ProgresoApiController::class, 'index']);

        /*
        |--------------------------------------------------------------------------
        | Pagos administrativos
        |--------------------------------------------------------------------------
        */

        Route::get('/pagos-admin', [PagoApiController::class, 'index']);
        Route::post('/pagos-admin', [PagoApiController::class, 'store']);

        /*
        |--------------------------------------------------------------------------
        | Historial de visitas administrativo
        |--------------------------------------------------------------------------
        */

        Route::post('/paciente/{id}/historial-visitas', [HistorialVisitaApiController::class, 'store']);
    });
});