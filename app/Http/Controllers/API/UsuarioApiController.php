<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\ConfirmarCorreoMailable;
use App\Services\NotificacionService;
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

    private function safeUserSelect(): array
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

    private function enviarCorreosConfirmacion(): bool
    {
        return filter_var(env('SEND_CONFIRMATION_EMAILS', false), FILTER_VALIDATE_BOOLEAN);
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
            $tipoUsuario = (int) $request->id_tipo_usuario;

            $tokenConfirmacion = null;
            $activo = 1;

            if ($tipoUsuario === 3) {
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
                'id_tipo_usuario' => $tipoUsuario,
                'activo' => $activo,
                'token_confirmacion' => $tokenConfirmacion,
            ]);

            $correoIntentado = false;
            $correoSeIntentaraDespues = false;

            if ($tipoUsuario === 3) {
                NotificacionService::crear(
                    (int) $id,
                    'Tu cuenta fue registrada correctamente. Revisa tu correo para confirmar tu cuenta.'
                );

                if ($this->enviarCorreosConfirmacion()) {
                    $correoIntentado = true;
                    $correoSeIntentaraDespues = true;

                    $correoDestino = $request->correo;
                    $nombreCompleto = trim($request->nombre . ' ' . $request->apaterno . ' ' . $request->amaterno);
                    $tokenParaCorreo = $tokenConfirmacion;
                    $idUsuario = $id;

                    /*
                     * Importante:
                     * Esto evita que el frontend se quede atorado en "Registrando...".
                     * Laravel responde primero al navegador y luego intenta mandar el correo.
                     */
                    app()->terminating(function () use ($correoDestino, $nombreCompleto, $tokenParaCorreo, $idUsuario) {
                        try {
                            Mail::to($correoDestino)->send(
                                new ConfirmarCorreoMailable($nombreCompleto, $tokenParaCorreo)
                            );

                            Log::info('Correo de confirmación enviado correctamente.', [
                                'id_usuario' => $idUsuario,
                                'correo' => $correoDestino,
                            ]);
                        } catch (\Throwable $mailException) {
                            Log::error('No se pudo enviar correo de confirmación: ' . $mailException->getMessage(), [
                                'id_usuario' => $idUsuario,
                                'correo' => $correoDestino,
                            ]);
                        }
                    });
                }
            }

            return response()->json([
                'success' => true,
                'message' => $this->mensajeRegistro($tipoUsuario, $correoIntentado),
                'id_usuario' => $id,
                'requiere_expediente' => $tipoUsuario === 3,
                'correo_intentado' => $correoIntentado,
                'correo_enviado' => false,
                'correo_se_intentara_despues' => $correoSeIntentaraDespues
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al registrar usuario: ' . $e->getMessage(), [
                'correo' => $request->correo ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    private function mensajeRegistro(int $tipoUsuario, bool $correoIntentado): string
    {
        if ($tipoUsuario !== 3) {
            return 'Usuario registrado exitosamente.';
        }

        if ($correoIntentado) {
            return 'Paciente registrado exitosamente. Se intentará enviar el correo de confirmación.';
        }

        return 'Paciente registrado exitosamente. El envío de correo de confirmación está desactivado temporalmente.';
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
            Log::error('Error al actualizar usuario: ' . $e->getMessage(), [
                'id_usuario' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar usuario: ' . $e->getMessage()
            ], 500);
        }
    }
}