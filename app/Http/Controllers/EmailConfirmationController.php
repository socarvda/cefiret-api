<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class EmailConfirmationController extends Controller
{
    public function confirm($token)
    {
        try {
            $user = DB::table('usuario')
                ->select('id_usuario')
                ->where('token_confirmacion', $token)
                ->first();

            if ($user) {
                DB::table('usuario')
                    ->where('id_usuario', $user->id_usuario)
                    ->update([
                        'activo' => 1,
                        'token_confirmacion' => null,
                    ]);

                return redirect(env('FRONTEND_URL') . '/views/auth/correo-confirmado.html?status=success');
            }

            return redirect(env('FRONTEND_URL') . '/views/auth/correo-confirmado.html?status=error');
        } catch (\Exception $e) {
            return redirect(env('FRONTEND_URL') . '/views/auth/correo-confirmado.html?status=error');
        }
    }
}