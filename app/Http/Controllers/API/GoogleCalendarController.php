<?php

namespace App\Http\Controllers;

use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoogleCalendarController extends Controller
{
    protected GoogleCalendarService $gcal;

    public function __construct(GoogleCalendarService $gcal)
    {
        $this->gcal = $gcal;
    }

    public function redirect()
    {
        return redirect($this->gcal->getAuthUrl());
    }

    public function callback(Request $request)
    {
        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://127.0.0.1:5500'), '/');

        if ($request->has('error')) {
            return redirect($frontendUrl . '/views/citas/index.html?google=error&message=' . urlencode($request->get('error')));
        }

        if (!$request->has('code')) {
            return redirect($frontendUrl . '/views/citas/index.html?google=error&message=' . urlencode('No se recibió código de autorización.'));
        }

        try {
            $this->gcal->handleCallback($request->get('code'));

            return redirect($frontendUrl . '/views/citas/index.html?google=success');
        } catch (\Exception $e) {
            return redirect($frontendUrl . '/views/citas/index.html?google=error&message=' . urlencode($e->getMessage()));
        }
    }

    public function disconnect()
    {
        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://127.0.0.1:5500'), '/');

        try {
            $this->gcal->revokeToken();

            return redirect($frontendUrl . '/views/citas/index.html?google=disconnected');
        } catch (\Exception $e) {
            return redirect($frontendUrl . '/views/citas/index.html?google=error&message=' . urlencode($e->getMessage()));
        }
    }

    public function status()
    {
        return response()->json([
            'success' => true,
            'google_connected' => $this->gcal->isAuthenticated(),
            'calendar_embed_url' => env('GOOGLE_CALENDAR_EMBED_URL', '')
        ]);
    }

    public function disponibilidad(Request $request)
    {
        $fecha = $request->get('fecha');
        $fisioterapeutaId = $request->get('fisioterapeuta_id');

        if (!$fecha) {
            return response()->json([
                'success' => false,
                'message' => 'Fecha requerida.'
            ], 400);
        }

        $horasBase = [
            '09:00', '10:00', '11:00', '12:00',
            '14:00', '15:00', '16:00', '17:00'
        ];

        $ocupadasBD = [];

        if ($fisioterapeutaId) {
            $ocupadasBD = DB::table('cita')
                ->where('id_fisioterapeuta', $fisioterapeutaId)
                ->where('fecha', $fecha)
                ->where('estatus', '!=', 'cancelada')
                ->pluck('hora')
                ->map(fn($h) => substr($h, 0, 5))
                ->toArray();
        }

        $ocupadasGoogle = $this->gcal->isAuthenticated()
            ? $this->gcal->getBusySlots($fecha)
            : [];

        $ocupadas = array_values(array_unique(array_merge($ocupadasBD, $ocupadasGoogle)));

        $disponibles = array_values(array_filter(
            $horasBase,
            fn($hora) => !in_array($hora, $ocupadas)
        ));

        return response()->json([
            'success' => true,
            'disponibles' => $disponibles,
            'ocupadas' => $ocupadas,
            'google_sync' => $this->gcal->isAuthenticated(),
        ]);
    }
}