<?php

namespace App\Http\Controllers\API;

use App\Mail\CambiarContrasenniaMailable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ResetPasswordApiController extends ApiController
{
    public function sendResetLinkEmail(Request $request)
    {
        $validated = $request->validate([
            'correo' => 'required|email',
        ]);

        $user = DB::table('usuario')
            ->select('id_usuario', 'nombre', 'apaterno', 'amaterno', 'correo', 'activo')
            ->where('correo', $validated['correo'])
            ->first();

        if (!$user) {
            return $this->error('Este correo no existe.', 404);
        }

        if ((int) ($user->activo ?? 1) !== 1) {
            return $this->error('Aun no confirmas tu correo.', 403);
        }

        $token = Str::uuid()->toString();
        $expiraEn = Carbon::now()->addMinutes(10);

        DB::table('usuario')->where('id_usuario', $user->id_usuario)->update([
            'token_recuperacion' => $token,
            'token_expiracion' => $expiraEn,
        ]);

        try {
            Mail::to($user->correo)->send(new CambiarContrasenniaMailable(
                trim($user->nombre . ' ' . $user->apaterno . ' ' . $user->amaterno),
                $token
            ));
        } catch (\Throwable) {
            // El token queda disponible en la respuesta para pruebas si el correo no esta configurado.
        }

        return $this->success(['token' => $token, 'expires_at' => $expiraEn], 'Revisa tu correo.');
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'contrasena' => 'required|string|min:8',
            'contrasena_confirmation' => 'required|same:contrasena',
        ]);

        $user = DB::table('usuario')->where('token_recuperacion', $validated['token'])->first();

        if (!$user || !$user->token_expiracion || Carbon::parse($user->token_expiracion)->isPast()) {
            return $this->error('Token invalido o expirado.', 422);
        }

        DB::table('usuario')->where('id_usuario', $user->id_usuario)->update([
            'contrasena' => Hash::make($validated['contrasena']),
            'token_recuperacion' => null,
            'token_expiracion' => null,
            'api_token' => null,
        ]);

        return $this->success(null, 'Contrasena actualizada.');
    }
}
