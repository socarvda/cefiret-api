<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmailConfirmationApiController extends ApiController
{
    public function confirm(Request $request, string $token)
    {
        $user = DB::table('usuario')->where('token_confirmacion', $token)->first();

        if (!$user) {
            return $this->error('Token de confirmacion invalido.', 404);
        }

        DB::table('usuario')->where('id_usuario', $user->id_usuario)->update([
            'activo' => 1,
            'token_confirmacion' => null,
        ]);

        return $this->success(null, 'Correo confirmado.');
    }
}
