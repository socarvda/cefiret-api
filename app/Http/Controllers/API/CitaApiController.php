<?php

namespace App\Http\Controllers\API;

use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CitaApiController extends ApiController
{
    public function __construct(private GoogleCalendarService $gcal)
    {
    }

    public function index(Request $request)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $citas = DB::table('cita')
            ->join('usuario as paciente', 'cita.id_usuario', '=', 'paciente.id_usuario')
            ->join('usuario as fisio', 'cita.id_fisioterapeuta', '=', 'fisio.id_usuario')
            ->select(
                'cita.*',
                'paciente.nombre as paciente_nombre',
                'paciente.apaterno as paciente_apaterno',
                'fisio.nombre as fisio_nombre',
                'fisio.apaterno as fisio_apaterno'
            )
            ->orderBy('cita.fecha')
            ->orderBy('cita.hora')
            ->get();

        return $this->success([
            'citas' => $citas,
            'google_connected' => $this->gcal->isAuthenticated(),
            'calendar_embed_url' => env('GOOGLE_CALENDAR_EMBED_URL', ''),
        ]);
    }

    public function opciones(Request $request)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        return $this->success([
            'pacientes' => DB::table('usuario')->where('id_tipo_usuario', 3)->orderBy('nombre')->get(),
            'fisioterapeutas' => DB::table('usuario')->where('id_tipo_usuario', 2)->orderBy('nombre')->get(),
            'google_connected' => $this->gcal->isAuthenticated(),
        ]);
    }

    public function store(Request $request)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $validated = $this->validatePayload($request);
        $conflicto = $this->hayConflicto($validated['fisioterapeuta_id'], $validated['fecha'], $validated['hora']);

        if ($conflicto) {
            return $this->error('El fisioterapeuta ya tiene una cita en esa fecha y hora.', 422);
        }

        if ($this->gcal->isAuthenticated() && in_array(substr($validated['hora'], 0, 5), $this->gcal->getBusySlots($validated['fecha']), true)) {
            return $this->error('Ese horario ya esta ocupado en Google Calendar.', 422);
        }

        $idCita = DB::table('cita')->insertGetId([
            'id_usuario' => $validated['paciente_id'],
            'id_fisioterapeuta' => $validated['fisioterapeuta_id'],
            'fecha' => $validated['fecha'],
            'hora' => $validated['hora'],
            'motivo' => $validated['motivo'] ?? null,
            'observaciones' => $validated['observaciones'] ?? null,
            'estatus' => 'programada',
            'google_event_id' => null,
        ]);

        $googleEventId = $this->crearEventoGoogle($idCita, $validated);
        if ($googleEventId) {
            DB::table('cita')->where('id_cita', $idCita)->update(['google_event_id' => $googleEventId]);
        }

        return $this->success(['id_cita' => $idCita, 'google_event_id' => $googleEventId], 'Cita creada.', 201);
    }

    public function show(Request $request, int $id)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $cita = DB::table('cita')->where('id_cita', $id)->first();

        return $cita ? $this->success($cita) : $this->error('Cita no encontrada.', 404);
    }

    public function update(Request $request, int $id)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $validated = $this->validatePayload($request);
        $actual = DB::table('cita')->where('id_cita', $id)->first();

        if (!$actual) {
            return $this->error('Cita no encontrada.', 404);
        }

        $conflicto = $this->hayConflicto($validated['fisioterapeuta_id'], $validated['fecha'], $validated['hora'], $id);
        if ($conflicto) {
            return $this->error('El fisioterapeuta ya tiene una cita en esa fecha y hora.', 422);
        }

        DB::table('cita')->where('id_cita', $id)->update([
            'id_usuario' => $validated['paciente_id'],
            'id_fisioterapeuta' => $validated['fisioterapeuta_id'],
            'fecha' => $validated['fecha'],
            'hora' => $validated['hora'],
            'motivo' => $validated['motivo'] ?? null,
            'observaciones' => $validated['observaciones'] ?? null,
        ]);

        if ($this->gcal->isAuthenticated() && $actual->google_event_id) {
            $this->gcal->deleteEvent($actual->google_event_id);
            DB::table('cita')->where('id_cita', $id)->update([
                'google_event_id' => $this->crearEventoGoogle($id, $validated),
            ]);
        }

        return $this->success(null, 'Cita actualizada.');
    }

    public function cancelar(Request $request, int $id)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $cita = DB::table('cita')->where('id_cita', $id)->first();
        if (!$cita) {
            return $this->error('Cita no encontrada.', 404);
        }

        DB::table('cita')->where('id_cita', $id)->update(['estatus' => 'cancelada']);

        if ($this->gcal->isAuthenticated() && $cita->google_event_id) {
            $this->gcal->cancelEvent($cita->google_event_id);
        }

        return $this->success(null, 'Cita cancelada.');
    }

    public function destroy(Request $request, int $id)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $cita = DB::table('cita')->where('id_cita', $id)->first();
        if (!$cita) {
            return $this->error('Cita no encontrada.', 404);
        }

        if ($this->gcal->isAuthenticated() && $cita->google_event_id) {
            $this->gcal->deleteEvent($cita->google_event_id);
        }

        DB::table('cita')->where('id_cita', $id)->delete();

        return $this->success(null, 'Cita eliminada.');
    }

    public function disponibilidad(Request $request)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $fecha = $request->get('fecha');
        $fisio = $request->get('fisioterapeuta_id');

        if (!$fecha) {
            return $this->error('Fecha requerida.', 422);
        }

        $horasBase = ['09:00', '10:00', '11:00', '12:00', '14:00', '15:00', '16:00', '17:00'];
        $ocupadasBD = $fisio
            ? DB::table('cita')
                ->where('id_fisioterapeuta', $fisio)
                ->where('fecha', $fecha)
                ->where('estatus', '!=', 'cancelada')
                ->pluck('hora')
                ->map(fn($hora) => substr($hora, 0, 5))
                ->toArray()
            : [];
        $ocupadasGoogle = $this->gcal->isAuthenticated() ? $this->gcal->getBusySlots($fecha) : [];
        $ocupadas = array_values(array_unique(array_merge($ocupadasBD, $ocupadasGoogle)));

        return $this->success([
            'disponibles' => array_values(array_filter($horasBase, fn($hora) => !in_array($hora, $ocupadas, true))),
            'ocupadas' => $ocupadas,
            'google_sync' => $this->gcal->isAuthenticated(),
        ]);
    }

    public function events(Request $request)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $rows = DB::table('cita')
            ->join('usuario as paciente', 'cita.id_usuario', '=', 'paciente.id_usuario')
            ->join('usuario as fisio', 'cita.id_fisioterapeuta', '=', 'fisio.id_usuario')
            ->select('cita.*', 'paciente.nombre as paciente', 'fisio.nombre as fisio')
            ->get();

        $events = $rows->map(function ($row) {
            return [
                'id' => $row->id_cita,
                'title' => trim(($row->paciente ?? '') . ' - ' . ($row->fisio ?? '')),
                'start' => $row->fecha . (!empty($row->hora) ? 'T' . substr($row->hora, 0, 5) : ''),
                'extendedProps' => [
                    'motivo' => $row->motivo,
                    'estatus' => $row->estatus ?? 'programada',
                ],
                'color' => match ($row->estatus ?? 'programada') {
                    'cancelada' => '#ef4444',
                    'completada' => '#22c55e',
                    default => '#3b82f6',
                },
            ];
        });

        return response()->json($events);
    }

    private function validatePayload(Request $request): array
    {
        $hora = $request->input('hora') ?: $request->input('hora_fallback');
        $request->merge(['hora' => $hora]);

        return $request->validate([
            'paciente_id' => 'required|integer|exists:usuario,id_usuario',
            'fisioterapeuta_id' => 'required|integer|exists:usuario,id_usuario',
            'fecha' => 'required|date',
            'hora' => 'required|string',
            'motivo' => 'nullable|string',
            'observaciones' => 'nullable|string',
        ]);
    }

    private function hayConflicto(int $fisioId, string $fecha, string $hora, ?int $excepto = null): bool
    {
        return DB::table('cita')
            ->when($excepto, fn($q) => $q->where('id_cita', '!=', $excepto))
            ->where('id_fisioterapeuta', $fisioId)
            ->where('fecha', $fecha)
            ->where('hora', $hora)
            ->where('estatus', '!=', 'cancelada')
            ->exists();
    }

    private function crearEventoGoogle(int $idCita, array $data): ?string
    {
        if (!$this->gcal->isAuthenticated()) {
            return null;
        }

        $paciente = DB::table('usuario')->where('id_usuario', $data['paciente_id'])->first();
        $fisio = DB::table('usuario')->where('id_usuario', $data['fisioterapeuta_id'])->first();

        return $this->gcal->createEvent([
            'id_cita' => $idCita,
            'paciente' => trim(($paciente->nombre ?? '') . ' ' . ($paciente->apaterno ?? '')),
            'fisioterapeuta' => trim(($fisio->nombre ?? '') . ' ' . ($fisio->apaterno ?? '')),
            'fecha' => $data['fecha'],
            'hora' => substr($data['hora'], 0, 5),
            'motivo' => $data['motivo'] ?? null,
        ]);
    }
}
