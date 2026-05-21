<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{
    protected function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function error(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    protected function userFromToken(Request $request): ?object
    {
        $token = $request->bearerToken();

        if (!$token) {
            return null;
        }

        return DB::table('usuario')->where('api_token', $token)->first();
    }

    protected function requireAuth(Request $request): ?JsonResponse
    {
        return $this->userFromToken($request)
            ? null
            : $this->error('No autenticado. Envia Authorization: Bearer <token>.', 401);
    }
}
