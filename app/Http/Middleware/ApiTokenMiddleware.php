<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiTokenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token no enviado.'
            ], 401);
        }

        $user = DB::table('usuario')
            ->select(
                'id_usuario',
                'nombre',
                'apaterno',
                'amaterno',
                'correo',
                'telefono',
                'fecha_nac',
                'id_tipo_usuario',
                'activo'
            )
            ->where('api_token', $token)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido.'
            ], 401);
        }

        if (($user->activo ?? 1) != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario inactivo o correo no confirmado.'
            ], 403);
        }

        $request->attributes->set('auth_user', $user);

        return $next($request);
    }
}