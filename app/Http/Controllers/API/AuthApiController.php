<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthApiController extends Controller
{
    public function login(Request $request)
    {
        /*
         * Aceptamos ambos nombres para evitar problemas con el frontend:
         * correo / email
         * contrasena / password
         */
        $correo = $request->input('correo', $request->input('email'));
        $contrasena = $request->input('contrasena', $request->input('password'));

        $request->merge([
            'correo' => $correo,
            'contrasena' => $contrasena,
        ]);

        $request->validate([
            'correo' => 'required|email',
            'contrasena' => 'required|string',
        ], [
            'correo.required' => 'El correo es obligatorio.',
            'correo.email' => 'Ingresa un correo válido.',
            'contrasena.required' => 'La contraseña es obligatoria.',
        ]);

        try {
            $usuario = DB::table('usuario')
                ->where('correo', $request->correo)
                ->first();

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Correo o contraseña incorrectos.'
                ], 401);
            }

            /*
             * Solo bloqueamos por correo no confirmado a pacientes.
             * Admins y fisioterapeutas anteriores pueden seguir entrando.
             */
            if ((int) $usuario->id_tipo_usuario === 3 && (int) ($usuario->activo ?? 0) !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aún no verificas tu correo.'
                ], 403);
            }

            $contrasenaGuardada = (string) $usuario->contrasena;

            $estaHasheada = strlen($contrasenaGuardada) === 60 &&
                (
                    str_starts_with($contrasenaGuardada, '$2y$') ||
                    str_starts_with($contrasenaGuardada, '$2a$') ||
                    str_starts_with($contrasenaGuardada, '$2b$')
                );

            if ($estaHasheada) {
                $passwordCorrecta = Hash::check($request->contrasena, $contrasenaGuardada);
            } else {
                $passwordCorrecta = hash_equals($contrasenaGuardada, $request->contrasena);
            }

            if (!$passwordCorrecta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Correo o contraseña incorrectos.'
                ], 401);
            }

            $token = Str::random(60);

            $updates = [
                'api_token' => $token
            ];

            /*
             * Si era una contraseña vieja en texto plano, la convertimos a hash
             * automáticamente después de iniciar sesión correctamente.
             */
            if (!$estaHasheada) {
                $updates['contrasena'] = Hash::make($request->contrasena);
            }

            DB::table('usuario')
                ->where('id_usuario', $usuario->id_usuario)
                ->update($updates);

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
                ->where('id_usuario', $usuario->id_usuario)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Inicio de sesión correcto.',
                'token' => $token,
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar sesión: ' . $e->getMessage()
            ], 500);
        }
    }

    public function me(Request $request)
    {
        $usuario = $request->attributes->get('auth_user');

        if (!$usuario) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario autenticado no encontrado.'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'user' => $usuario
        ]);
    }

    public function logout(Request $request)
    {
        $usuario = $request->attributes->get('auth_user');

        if ($usuario) {
            DB::table('usuario')
                ->where('id_usuario', $usuario->id_usuario)
                ->update([
                    'api_token' => null
                ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente.'
        ]);
    }
}