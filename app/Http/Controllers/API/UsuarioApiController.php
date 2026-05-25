<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\ConfirmarCorreoMailable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UsuarioApiController extends Controller
{
    private const PASSWORD_REGEX = '/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/';

    private const PASSWORD_MESSAGE = 'La contraseña debe tener mínimo 8 caracteres, una letra mayúscula, un número y un carácter especial.';

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
                'message' => 'Usuario no encontrado.'
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
            'contrasena' => [
                'required',
                'string',
                'regex:' . self::PASSWORD_REGEX,
            ],
            'id_tipo_usuario' => 'required|in:1,2,3'
        ], [
            'correo.required' => 'El correo es obligatorio.',
            'correo.email' => 'Ingresa un correo válido.',
            'correo.unique' => 'Este correo ya está registrado.',
            'contrasena.required' => 'La contraseña es obligatoria.',
            'contrasena.regex' => self::PASSWORD_MESSAGE,
            'id_tipo_usuario.required' => 'Selecciona un tipo de usuario.',
            'id_tipo_usuario.in' => 'El tipo de usuario no es válido.',
        ]);

        try {
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

            $correoEnviado = true;

            if ((int) $request->id_tipo_usuario === 3) {
                try {
                    $nombreCompleto = trim($request->nombre . ' ' . $request->apaterno . ' ' . $request->amaterno);

                    Mail::to($request->correo)->send(
                        new ConfirmarCorreoMailable($nombreCompleto, $tokenConfirmacion)
                    );
                } catch (\Exception $mailException) {
                    $correoEnviado = false;

                    Log::warning('No se pudo enviar correo de confirmación: ' . $mailException->getMessage(), [
    'id_usuario' => $id,
    'correo' => $request->correo,
]);
                }
            }

            if ((int) $request->id_tipo_usuario === 3 && !$correoEnviado) {
                return response()->json([
                    'success' => true,
                    'message' => 'Paciente registrado, pero no se pudo enviar el correo de confirmación. Revisa la configuración del correo.',
                    'id_usuario' => $id,
                    'requiere_expediente' => true,
                    'correo_enviado' => false
                ], 201);
            }

            return response()->json([
                'success' => true,
                'message' => (int) $request->id_tipo_usuario === 3
                    ? 'Paciente registrado exitosamente. Debe confirmar su correo.'
                    : 'Usuario registrado exitosamente.',
                'id_usuario' => $id,
                'requiere_expediente' => (int) $request->id_tipo_usuario === 3,
                'correo_enviado' => $correoEnviado
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $usuario = DB::table('usuario')
            ->where('id_usuario', $id)
            ->first();

        if (!$usuario) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.'
            ], 404);
        }

        $request->validate([
            'nombre' => 'required|string|max:100',
            'apaterno' => 'required|string|max:100',
            'amaterno' => 'required|string|max:100',
            'correo' => 'required|email|unique:usuario,correo,' . $id . ',id_usuario',
            'telefono' => 'required|string|max:20',
            'fecha_nac' => 'required|date',
            'id_tipo_usuario' => 'nullable|in:1,2,3',
            'activo' => 'nullable|in:0,1',
            'contrasena' => [
                'nullable',
                'string',
                'regex:' . self::PASSWORD_REGEX,
            ],
        ], [
            'correo.required' => 'El correo es obligatorio.',
            'correo.email' => 'Ingresa un correo válido.',
            'correo.unique' => 'Este correo ya está registrado.',
            'contrasena.regex' => self::PASSWORD_MESSAGE,
            'id_tipo_usuario.in' => 'El tipo de usuario no es válido.',
            'activo.in' => 'El estado del usuario no es válido.',
        ]);

        try {
            $data = [
                'nombre' => $request->nombre,
                'apaterno' => $request->apaterno,
                'amaterno' => $request->amaterno,
                'correo' => $request->correo,
                'telefono' => $request->telefono,
                'fecha_nac' => $request->fecha_nac,
            ];

            if ($request->filled('id_tipo_usuario')) {
                $data['id_tipo_usuario'] = $request->id_tipo_usuario;
            }

            if ($request->has('activo')) {
                $data['activo'] = $request->activo;
            }

            if ($request->filled('contrasena')) {
                $data['contrasena'] = Hash::make($request->contrasena);
                $data['api_token'] = null;
            }

            DB::table('usuario')
                ->where('id_usuario', $id)
                ->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Usuario actualizado correctamente.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar usuario: ' . $e->getMessage()
            ], 500);
        }
    }
}