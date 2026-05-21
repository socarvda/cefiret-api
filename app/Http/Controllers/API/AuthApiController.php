<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthApiController extends ApiController
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = DB::table('usuario')->where('correo', $validated['email'])->first();

        if (!$user) {
            return $this->error('Correo o contrasena incorrectos.', 401);
        }

        $password = $user->contrasena ?? '';
        $valid = str_starts_with($password, '$2y$') || str_starts_with($password, '$2a$') || str_starts_with($password, '$argon')
            ? Hash::check($validated['password'], $password)
            : hash_equals($password, $validated['password']);

        if (!$valid) {
            return $this->error('Correo o contrasena incorrectos.', 401);
        }

        if ((int) ($user->activo ?? 1) !== 1) {
            return $this->error('Aun no confirmas tu correo.', 403);
        }

        $token = hash('sha256', Str::random(80));
        $updates = ['api_token' => $token];

        if (!str_starts_with($password, '$2y$') && !str_starts_with($password, '$2a$') && !str_starts_with($password, '$argon')) {
            $updates['contrasena'] = Hash::make($validated['password']);
        }

        DB::table('usuario')->where('id_usuario', $user->id_usuario)->update($updates);

        $freshUser = DB::table('usuario')
            ->select('id_usuario', 'nombre', 'apaterno', 'amaterno', 'correo', 'telefono', 'fecha_nac', 'id_tipo_usuario', 'activo')
            ->where('id_usuario', $user->id_usuario)
            ->first();

        return $this->success([
            'token' => $token,
            'user' => $freshUser,
        ], 'Login correcto.');
    }

    public function me(Request $request)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $user = DB::table('usuario')
            ->select('id_usuario', 'nombre', 'apaterno', 'amaterno', 'correo', 'telefono', 'fecha_nac', 'id_tipo_usuario', 'activo')
            ->where('api_token', $request->bearerToken())
            ->first();

        return $this->success($user);
    }

    public function logout(Request $request)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        DB::table('usuario')->where('api_token', $request->bearerToken())->update(['api_token' => null]);

        return $this->success(null, 'Sesion cerrada.');
    }
}
