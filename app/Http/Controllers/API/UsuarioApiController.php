<?php

namespace App\Http\Controllers\API;

use App\Mail\ConfirmarCorreoMailable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UsuarioApiController extends ApiController
{
    public function index(Request $request)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $query = trim((string) $request->get('q', ''));

        $usuarios = DB::table('usuario')
            ->select('id_usuario', 'nombre', 'apaterno', 'amaterno', 'fecha_nac', 'telefono', 'correo', 'id_tipo_usuario', 'activo')
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($q) use ($query) {
                    $q->where('nombre', 'like', "%{$query}%")
                        ->orWhere('apaterno', 'like', "%{$query}%")
                        ->orWhere('amaterno', 'like', "%{$query}%")
                        ->orWhere('correo', 'like', "%{$query}%")
                        ->orWhere('telefono', 'like', "%{$query}%");
                });
            })
            ->orderBy('nombre')
            ->get();

        return $this->success($usuarios);
    }

    public function store(Request $request)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:50',
            'apaterno' => 'required|string|max:50',
            'amaterno' => 'nullable|string|max:50',
            'correo' => 'required|email|max:100|unique:usuario,correo',
            'telefono' => 'required|string|max:20',
            'fecha_nac' => 'required|date',
            'contrasena' => 'required|string|min:6',
            'id_tipo_usuario' => 'required|integer|in:1,2,3',
        ]);

        $tokenConfirmacion = null;
        $activo = 1;

        if ((int) $validated['id_tipo_usuario'] === 3) {
            $activo = 0;
            $tokenConfirmacion = Str::uuid()->toString();
        }

        $id = DB::table('usuario')->insertGetId([
            'nombre' => $validated['nombre'],
            'apaterno' => $validated['apaterno'],
            'amaterno' => $validated['amaterno'] ?? '',
            'correo' => $validated['correo'],
            'contrasena' => Hash::make($validated['contrasena']),
            'telefono' => $validated['telefono'],
            'fecha_nac' => $validated['fecha_nac'],
            'id_tipo_usuario' => $validated['id_tipo_usuario'],
            'activo' => $activo,
            'token_confirmacion' => $tokenConfirmacion,
        ]);

        if ((int) $validated['id_tipo_usuario'] === 3) {
            try {
                Mail::to($validated['correo'])->send(new ConfirmarCorreoMailable(
                    trim($validated['nombre'] . ' ' . $validated['apaterno'] . ' ' . ($validated['amaterno'] ?? '')),
                    $tokenConfirmacion
                ));
            } catch (\Throwable) {
                // El usuario queda creado aunque el correo falle; el API reporta el token para pruebas locales.
            }
        }

        return $this->success([
            'id_usuario' => $id,
            'token_confirmacion' => $tokenConfirmacion,
        ], 'Usuario creado.', 201);
    }

    public function show(Request $request, int $id)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $usuario = DB::table('usuario')
            ->select('id_usuario', 'nombre', 'apaterno', 'amaterno', 'fecha_nac', 'telefono', 'correo', 'id_tipo_usuario', 'activo')
            ->where('id_usuario', $id)
            ->first();

        return $usuario ? $this->success($usuario) : $this->error('Usuario no encontrado.', 404);
    }

    public function update(Request $request, int $id)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:50',
            'apaterno' => 'required|string|max:50',
            'amaterno' => 'nullable|string|max:50',
            'correo' => "required|email|max:100|unique:usuario,correo,{$id},id_usuario",
            'telefono' => 'required|string|max:20',
            'fecha_nac' => 'required|date',
            'id_tipo_usuario' => 'nullable|integer|in:1,2,3',
            'activo' => 'nullable|integer|in:0,1',
        ]);

        $exists = DB::table('usuario')->where('id_usuario', $id)->exists();
        if (!$exists) {
            return $this->error('Usuario no encontrado.', 404);
        }

        DB::table('usuario')->where('id_usuario', $id)->update(array_filter([
            'nombre' => $validated['nombre'],
            'apaterno' => $validated['apaterno'],
            'amaterno' => $validated['amaterno'] ?? '',
            'correo' => $validated['correo'],
            'telefono' => $validated['telefono'],
            'fecha_nac' => $validated['fecha_nac'],
            'id_tipo_usuario' => $validated['id_tipo_usuario'] ?? null,
            'activo' => $validated['activo'] ?? null,
        ], fn($value) => $value !== null));

        return $this->success(null, 'Usuario actualizado.');
    }
}
