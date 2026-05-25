<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\CambiarContrasenniaMailable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ResetPasswordApiController extends Controller
{
    private const PASSWORD_REGEX = '/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/';

    private const PASSWORD_MESSAGE = 'La contraseña debe tener mínimo 8 caracteres, una letra mayúscula, un número y un carácter especial.';

    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'correo' => 'required|email'
        ]);

        try {
            $user = DB::table('usuario')
                ->select('id_usuario', 'nombre', 'apaterno', 'amaterno', 'activo', 'correo')
                ->where('correo', $request->correo)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este correo no existe.'
                ], 404);
            }

            if (($user->activo ?? 1) != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aún no confirmas tu correo.'
                ], 403);
            }

            $nombre = trim($user->nombre . ' ' . $user->apaterno . ' ' . $user->amaterno);
            $token = Str::uuid()->toString();
            $expiraEn = Carbon::now()->addMinutes(10);

            DB::table('usuario')
                ->where('correo', $request->correo)
                ->update([
                    'token_recuperacion' => $token,
                    'token_expiracion' => $expiraEn,
                ]);

            Mail::to($request->correo)->send(new CambiarContrasenniaMailable($nombre, $token));

            return response()->json([
                'success' => true,
                'message' => 'Revisa tu correo para cambiar la contraseña.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hubo un error en el servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'contrasennia' => [
                'required',
                'string',
                'regex:' . self::PASSWORD_REGEX,
            ],
            'recontrasennia' => 'required|same:contrasennia',
            'mytoken' => 'required'
        ], [
            'contrasennia.regex' => self::PASSWORD_MESSAGE,
        ]);

        try {
            $user = DB::table('usuario')
                ->where('token_recuperacion', $request->mytoken)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token inválido.'
                ], 404);
            }

            if (!$user->token_expiracion || Carbon::parse($user->token_expiracion)->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El token ya expiró. Solicita uno nuevo.'
                ], 410);
            }

            DB::table('usuario')
                ->where('id_usuario', $user->id_usuario)
                ->update([
                    'contrasena' => Hash::make($request->contrasennia),
                    'token_recuperacion' => null,
                    'token_expiracion' => null,
                    'api_token' => null,
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Contraseña actualizada correctamente.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hubo un error en el servidor: ' . $e->getMessage()
            ], 500);
        }
    }
}