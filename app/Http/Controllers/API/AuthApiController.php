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
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = DB::table('usuario')->where('correo', $request->email)->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Correo o contraseña incorrectos'], 401);
        }

        $passwordValid = false;

        if (strlen($user->contrasena) === 60 && str_starts_with($user->contrasena, '$2y$')) {
            $passwordValid = Hash::check($request->password, $user->contrasena);
        } else {
            $passwordValid = $user->contrasena === $request->password;

            if ($passwordValid) {
                DB::table('usuario')
                    ->where('id_usuario', $user->id_usuario)
                    ->update(['contrasena' => Hash::make($request->password)]);
            }
        }

        if (!$passwordValid) {
            return response()->json(['success' => false, 'message' => 'Correo o contraseña incorrectos'], 401);
        }

        if (($user->activo ?? 1) != 1) {
            return response()->json(['success' => false, 'message' => 'Aún no confirmas tu correo.'], 403);
        }

        $token = Str::random(80);

        DB::table('usuario')
            ->where('id_usuario', $user->id_usuario)
            ->update(['api_token' => $token]);

        return response()->json([
            'success' => true,
            'message' => 'Login correcto',
            'token' => $token,
            'user' => [
                'id_usuario' => $user->id_usuario,
                'nombre' => $user->nombre,
                'apaterno' => $user->apaterno,
                'amaterno' => $user->amaterno,
                'correo' => $user->correo,
                'id_tipo_usuario' => $user->id_tipo_usuario,
            ]
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario autenticado no encontrado.'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        if ($user) {
            DB::table('usuario')
                ->where('id_usuario', $user->id_usuario)
                ->update(['api_token' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente'
        ]);
    }
}
