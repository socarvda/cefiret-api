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
            'contrasennia' => 'required|min:8',
            'recontrasennia' => 'required|same:contrasennia',
            'mytoken' => 'required'
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

            if ($user->token_expiracion && Carbon::parse($user->token_expiracion)->lessThan(Carbon::now())) {
                return response()->json([
                    'success' => false,
                    'message' => 'El enlace ha expirado.'
                ], 410);
            }

            DB::table('usuario')
                ->where('token_recuperacion', $request->mytoken)
                ->update([
                    'contrasena' => Hash::make($request->contrasennia),
                    'token_recuperacion' => null,
                    'token_expiracion' => null,
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Cambio de contraseña exitoso.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hubo un error en el servidor: ' . $e->getMessage()
            ], 500);
        }
    }
}