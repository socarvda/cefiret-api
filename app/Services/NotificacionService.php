<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NotificacionService
{
    public static function crear(int $idUsuario, string $mensaje): void
    {
        try {
            DB::table('notificacion')->insert([
                'id_usuario' => $idUsuario,
                'mensaje' => $mensaje,
                'fecha' => now(),
                'leida' => 0,
            ]);
        } catch (\Exception $e) {
            // No detenemos el sistema si falla una notificación.
            logger()->warning('Error al crear notificación: ' . $e->getMessage());
        }
    }
}