<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardApiController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $request->attributes->get('auth_user');

        if (!$usuario) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario autenticado no encontrado.'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'usuario' => $usuario
        ]);
    }
}
