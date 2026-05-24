<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificacionApiController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $request->attributes->get('auth_user');

        if (!$usuario) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario autenticado no encontrado.'
            ], 401);
        }

        $notificaciones = DB::table('notificacion')
            ->where('id_usuario', $usuario->id_usuario)
            ->orderBy('fecha', 'desc')
            ->get();

        $noLeidas = DB::table('notificacion')
            ->where('id_usuario', $usuario->id_usuario)
            ->where('leida', 0)
            ->count();

        return response()->json([
            'success' => true,
            'notificaciones' => $notificaciones,
            'no_leidas' => $noLeidas
        ]);
    }

    public function paciente(Request $request, $id)
    {
        $usuario = $request->attributes->get('auth_user');

        if (!$usuario) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario autenticado no encontrado.'
            ], 401);
        }

        /*
         * Reglas:
         * - Administrador y fisioterapeuta pueden consultar notificaciones de cualquier paciente.
         * - Paciente solo puede consultar sus propias notificaciones.
         */
        if ((int) $usuario->id_tipo_usuario === 3 && (int) $usuario->id_usuario !== (int) $id) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para consultar estas notificaciones.'
            ], 403);
        }

        $notificaciones = DB::table('notificacion')
            ->where('id_usuario', $id)
            ->orderBy('fecha', 'desc')
            ->get();

        $noLeidas = DB::table('notificacion')
            ->where('id_usuario', $id)
            ->where('leida', 0)
            ->count();

        return response()->json([
            'success' => true,
            'notificaciones' => $notificaciones,
            'no_leidas' => $noLeidas
        ]);
    }

    public function noLeidas(Request $request)
    {
        $usuario = $request->attributes->get('auth_user');

        if (!$usuario) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario autenticado no encontrado.'
            ], 401);
        }

        $total = DB::table('notificacion')
            ->where('id_usuario', $usuario->id_usuario)
            ->where('leida', 0)
            ->count();

        return response()->json([
            'success' => true,
            'no_leidas' => $total
        ]);
    }

    public function marcarLeida(Request $request, $id)
    {
        $usuario = $request->attributes->get('auth_user');

        if (!$usuario) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario autenticado no encontrado.'
            ], 401);
        }

        $notificacion = DB::table('notificacion')
            ->where('id_notificacion', $id)
            ->first();

        if (!$notificacion) {
            return response()->json([
                'success' => false,
                'message' => 'Notificación no encontrada.'
            ], 404);
        }

        if ((int) $notificacion->id_usuario !== (int) $usuario->id_usuario && (int) $usuario->id_tipo_usuario === 3) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para modificar esta notificación.'
            ], 403);
        }

        DB::table('notificacion')
            ->where('id_notificacion', $id)
            ->update([
                'leida' => 1
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Notificación marcada como leída.'
        ]);
    }

    public function marcarTodasLeidas(Request $request)
    {
        $usuario = $request->attributes->get('auth_user');

        if (!$usuario) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario autenticado no encontrado.'
            ], 401);
        }

        DB::table('notificacion')
            ->where('id_usuario', $usuario->id_usuario)
            ->update([
                'leida' => 1
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Todas las notificaciones fueron marcadas como leídas.'
        ]);
    }
}