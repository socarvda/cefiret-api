<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiTokenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token no enviado.'
            ], 401);
        }

        $user = DB::table('usuario')->where('api_token', $token)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido.'
            ], 401);
        }

        $request->merge(['auth_user' => $user]);

        return $next($request);
    }
}