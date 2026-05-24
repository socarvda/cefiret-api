<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\ConfirmarCorreoMailable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UsuarioApiController extends Controller
{
    private function safeUserSelect()
    {
        return [
            'id_usuario',
            'nombre',
            'apaterno',
            'amaterno',
            'correo',
            'telefono',
            'fecha_nac',
            'id_tipo_usuario',
            'activo'
        ];
    }

    public function index(Request $request)
    {
        $query = $request->get('query');

        $usuarios = DB::table('usuario')
            ->select($this->safeUserSelect())
            ->when($query, function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('nombre', 'like', "%$query%")
                        ->orWhere('apaterno', 'like', "%$query%")
                        ->orWhere('amaterno', 'like', "%$query%")
                        ->orWhere('correo', 'like', "%$query%")
                        ->orWhere('telefono', 'like', "%$query%");
                });
            })
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'success' => true,
            'usuarios' => $usuarios
        ]);
    }

    public function show($id)
    {
        $usuario = DB::table('usuario')
            ->select($this->safeUserSelect())
            ->where('id_usuario', $id)
            ->first();

        if (!$usuario) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'usuario' => $usuario
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'apaterno' => 'required|string|max:100',
            'amaterno' => 'required|string|max:100',
            'correo' => 'required|email|unique:usuario,correo',
            'telefono' => 'required|string|max:20',
            'fecha_nac' => 'required|date',
            'contrasena' => 'required|min:6',
            'id_tipo_usuario' => 'required|in:1,2,3'
        ]);

        $tokenConfirmacion = null;
        $activo = 1;

        if ((int) $request->id_tipo_usuario === 3) {
            $activo = 0;
            $tokenConfirmacion = Str::uuid()->toString();
        }

        $id = DB::table('usuario')->insertGetId([
            'nombre' => $request->nombre,
            'apaterno' => $request->apaterno,
            'amaterno' => $request->amaterno,
            'correo' => $request->correo,
            'contrasena' => Hash::make($request->contrasena),
            'telefono' => $request->telefono,
            'fecha_nac' => $request->fecha_nac,
            'id_tipo_usuario' => $request->id_tipo_usuario,
            'activo' => $activo,
            'token_confirmacion' => $tokenConfirmacion,
        ]);

        if ((int) $request->id_tipo_usuario === 3) {
            $nombreCompleto = trim($request->nombre . ' ' . $request->apaterno . ' ' . $request->amaterno);
            Mail::to($request->correo)->send(new ConfirmarCorreoMailable($nombreCompleto, $tokenConfirmacion));
        }

        return response()->json([
            'success' => true,
            'message' => 'Usuario registrado exitosamente.',
            'id_usuario' => $id,
            'requiere_expediente' => (int) $request->id_tipo_usuario === 3
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'apaterno' => 'required|string|max:100',
            'amaterno' => 'required|string|max:100',
            'correo' => 'required|email|unique:usuario,correo,' . $id . ',id_usuario',
            'telefono' => 'required|string|max:20',
            'fecha_nac' => 'required|date'
        ]);

        $existe = DB::table('usuario')->where('id_usuario', $id)->exists();

        if (!$existe) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        DB::table('usuario')->where('id_usuario', $id)->update([
            'nombre' => $request->nombre,
            'apaterno' => $request->apaterno,
            'amaterno' => $request->amaterno,
            'correo' => $request->correo,
            'telefono' => $request->telefono,
            'fecha_nac' => $request->fecha_nac
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente'
        ]);
    }
}