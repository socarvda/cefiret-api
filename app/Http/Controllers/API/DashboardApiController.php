<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardApiController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'usuario' => $request->auth_user
        ]);
    }
}