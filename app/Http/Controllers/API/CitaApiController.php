<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CitaApiController extends Controller
{
    protected GoogleCalendarService $gcal;

    public function __construct(GoogleCalendarService $gcal)
    {
        $this->gcal = $gcal;
    }

    public function index()
    {
        try {
            $citas = DB::table('cita')
                ->join('usuario as paciente', 'cita.id_usuario', '=', 'paciente.id_usuario')
                ->join('usuario as fisio', 'cita.id_fisioterapeuta', '=', 'fisio.id_usuario')
                ->select(
                    'cita.*',
                    'paciente.nombre as paciente',
                    'paciente.apaterno as paciente_apaterno',
                    'paciente.amaterno as paciente_amaterno',
                    'fisio.nombre as fisio',
                    'fisio.apaterno as fisio_apaterno',
                    'fisio.amaterno as fisio_amaterno'
                )
                ->orderBy('cita.fecha')
                ->orderBy('cita.hora')
                ->get();

            $pacientes = DB::table('usuario')->where('id_tipo_usuario', 3)->orderBy('nombre')->get();
            $fisios = DB::table('usuario')->where('id_tipo_usuario', 2)->orderBy('nombre')->get();

            return response()->json([
                'success' => true,
                'citas' => $citas,
                'pacientes' => $pacientes,
                'fisios' => $fisios,
                'google_connected' => $this->gcal->isAuthenticated(),
                'calendar_embed_url' => env('GOOGLE_CALENDAR_EMBED_URL', '')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar las citas: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $hora = $request->input('hora') ?: $request->input('hora_fallback');
        $request->merge(['hora' => $hora]);

        $request->validate([
            'paciente_id' => 'required|integer',
            'fisioterapeuta_id' => 'required|integer',
            'fecha' => 'required|date',
            'hora' => 'required'
        ]);

        try {
            $existe = DB::table('cita')
                ->where('id_fisioterapeuta', $request->fisioterapeuta_id)
                ->where('fecha', $request->fecha)
                ->where('hora', $request->hora)
                ->where('estatus', '!=', 'cancelada')
                ->exists();

            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'El fisioterapeuta ya tiene una cita en esa fecha y hora.'
                ], 409);
            }

            if ($this->gcal->isAuthenticated()) {
                $ocupadasGoogle = $this->gcal->getBusySlots($request->fecha);
                $horaCorta = substr($request->hora, 0, 5);

                if (in_array($horaCorta, $ocupadasGoogle)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ese horario ya está ocupado en Google Calendar.'
                    ], 409);
                }
            }

            $paciente = DB::table('usuario')->where('id_usuario', $request->paciente_id)->first();
            $fisio = DB::table('usuario')->where('id_usuario', $request->fisioterapeuta_id)->first();

            if (!$paciente || !$fisio) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paciente o fisioterapeuta no encontrado.'
                ], 404);
            }

            $citaId = DB::table('cita')->insertGetId([
                'id_usuario' => $request->paciente_id,
                'id_fisioterapeuta' => $request->fisioterapeuta_id,
                'fecha' => $request->fecha,
                'hora' => $request->hora,
                'motivo' => $request->motivo,
                'estatus' => 'programada',
                'google_event_id' => null,
            ]);

            if ($this->gcal->isAuthenticated()) {
                $googleEventId = $this->gcal->createEvent([
                    'id_cita' => $citaId,
                    'paciente' => $paciente->nombre . ' ' . $paciente->apaterno,
                    'fisioterapeuta' => $fisio->nombre . ' ' . $fisio->apaterno,
                    'fecha' => $request->fecha,
                    'hora' => substr($request->hora, 0, 5),
                    'motivo' => $request->motivo,
                ]);

                if ($googleEventId) {
                    DB::table('cita')
                        ->where('id_cita', $citaId)
                        ->update(['google_event_id' => $googleEventId]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Cita creada correctamente.',
                'id_cita' => $citaId,
                'google_sync' => $this->gcal->isAuthenticated()
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la cita: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $cita = DB::table('cita')
            ->join('usuario as paciente', 'cita.id_usuario', '=', 'paciente.id_usuario')
            ->join('usuario as fisio', 'cita.id_fisioterapeuta', '=', 'fisio.id_usuario')
            ->where('cita.id_cita', $id)
            ->select(
                'cita.*',
                'paciente.nombre as paciente',
                'paciente.apaterno as paciente_apaterno',
                'paciente.amaterno as paciente_amaterno',
                'fisio.nombre as fisio',
                'fisio.apaterno as fisio_apaterno',
                'fisio.amaterno as fisio_amaterno'
            )
            ->first();

        if (!$cita) {
            return response()->json([
                'success' => false,
                'message' => 'Cita no encontrada.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'cita' => $cita
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'paciente_id' => 'required|integer',
            'fisioterapeuta_id' => 'required|integer',
            'fecha' => 'required|date',
            'hora' => 'required'
        ]);

        try {
            $existe = DB::table('cita')
                ->where('id_cita', '!=', $id)
                ->where('id_fisioterapeuta', $request->fisioterapeuta_id)
                ->where('fecha', $request->fecha)
                ->where('hora', $request->hora)
                ->where('estatus', '!=', 'cancelada')
                ->exists();

            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'El fisioterapeuta ya tiene una cita en esa fecha y hora.'
                ], 409);
            }

            $citaActual = DB::table('cita')->where('id_cita', $id)->first();

            if (!$citaActual) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cita no encontrada.'
                ], 404);
            }

            DB::table('cita')
                ->where('id_cita', $id)
                ->update([
                    'id_usuario' => $request->paciente_id,
                    'id_fisioterapeuta' => $request->fisioterapeuta_id,
                    'fecha' => $request->fecha,
                    'hora' => $request->hora,
                    'motivo' => $request->motivo,
                ]);

            if ($this->gcal->isAuthenticated() && $citaActual->google_event_id) {
                $this->gcal->deleteEvent($citaActual->google_event_id);

                $paciente = DB::table('usuario')->where('id_usuario', $request->paciente_id)->first();
                $fisio = DB::table('usuario')->where('id_usuario', $request->fisioterapeuta_id)->first();

                $newGoogleEventId = $this->gcal->createEvent([
                    'id_cita' => $id,
                    'paciente' => $paciente->nombre . ' ' . $paciente->apaterno,
                    'fisioterapeuta' => $fisio->nombre . ' ' . $fisio->apaterno,
                    'fecha' => $request->fecha,
                    'hora' => substr($request->hora, 0, 5),
                    'motivo' => $request->motivo,
                ]);

                DB::table('cita')
                    ->where('id_cita', $id)
                    ->update(['google_event_id' => $newGoogleEventId]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Cita actualizada correctamente.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la cita: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $cita = DB::table('cita')->where('id_cita', $id)->first();

            if (!$cita) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cita no encontrada.'
                ], 404);
            }

            if ($this->gcal->isAuthenticated() && $cita->google_event_id) {
                $this->gcal->deleteEvent($cita->google_event_id);
            }

            DB::table('cita')->where('id_cita', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cita eliminada correctamente.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la cita: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cancelar($id)
    {
        try {
            $cita = DB::table('cita')->where('id_cita', $id)->first();

            if (!$cita) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cita no encontrada.'
                ], 404);
            }

            DB::table('cita')
                ->where('id_cita', $id)
                ->update(['estatus' => 'cancelada']);

            if ($this->gcal->isAuthenticated() && $cita->google_event_id) {
                $this->gcal->cancelEvent($cita->google_event_id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Cita cancelada correctamente.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar la cita: ' . $e->getMessage()
            ], 500);
        }
    }

    public function disponibilidad(Request $request)
    {
        $fisio = $request->get('fisioterapeuta_id');
        $fecha = $request->get('fecha');

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

        $horasOcupadasBD = [];

        if ($fisio) {
            $horasOcupadasBD = DB::table('cita')
                ->where('id_fisioterapeuta', $fisio)
                ->where('fecha', $fecha)
                ->where('estatus', '!=', 'cancelada')
                ->pluck('hora')
                ->map(fn($h) => substr($h, 0, 5))
                ->toArray();
        }

        $horasOcupadasGoogle = $this->gcal->isAuthenticated()
            ? $this->gcal->getBusySlots($fecha)
            : [];

        $ocupadas = array_unique(array_merge($horasOcupadasBD, $horasOcupadasGoogle));
        $disponibles = array_values(array_filter($horasBase, fn($hora) => !in_array($hora, $ocupadas)));

        return response()->json([
            'success' => true,
            'disponibles' => $disponibles,
            'ocupadas' => array_values($ocupadas),
            'google_sync' => $this->gcal->isAuthenticated(),
        ]);
    }

    public function events()
    {
        try {
            $rows = DB::table('cita')
                ->join('usuario as paciente', 'cita.id_usuario', '=', 'paciente.id_usuario')
                ->join('usuario as fisio', 'cita.id_fisioterapeuta', '=', 'fisio.id_usuario')
                ->select('cita.*', 'paciente.nombre as paciente', 'fisio.nombre as fisio')
                ->get();

            $events = [];

            foreach ($rows as $r) {
                $start = $r->fecha;

                if (!empty($r->hora)) {
                    $start .= 'T' . substr($r->hora, 0, 5);
                }

                $events[] = [
                    'id' => $r->id_cita,
                    'title' => ($r->paciente ?? '') . ' - ' . ($r->fisio ?? ''),
                    'start' => $start,
                    'extendedProps' => [
                        'motivo' => $r->motivo,
                        'estatus' => $r->estatus ?? 'programada',
                    ],
                    'color' => match ($r->estatus ?? 'programada') {
                        'cancelada' => '#ef4444',
                        'completada' => '#22c55e',
                        default => '#3b82f6',
                    },
                ];
            }

            return response()->json($events);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar los eventos: ' . $e->getMessage()
            ], 500);
        }
    }
}