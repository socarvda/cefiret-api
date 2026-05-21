<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgresoApiController extends ApiController
{
    public function pacientes(Request $request)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $pacientes = DB::table('usuario')
            ->where('id_tipo_usuario', 3)
            ->select('id_usuario', 'nombre', 'apaterno', 'amaterno', 'correo', 'telefono')
            ->orderBy('nombre')
            ->get();

        return $this->success($pacientes);
    }

    public function show(Request $request, int $idPaciente)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $paciente = DB::table('usuario')
            ->select('id_usuario', 'nombre', 'apaterno', 'amaterno', 'correo', 'telefono')
            ->where('id_usuario', $idPaciente)
            ->where('id_tipo_usuario', 3)
            ->first();

        if (!$paciente) {
            return $this->error('Paciente no encontrado.', 404);
        }

        $rutina = $this->ultimaRutinaPaciente($idPaciente);
        $progresos = $this->progresosPaciente($idPaciente);

        return $this->success([
            'paciente' => $paciente,
            'ultima_rutina' => $rutina,
            'progresos' => $progresos,
            'average_progress' => round((float) ($progresos->avg('porcentaje') ?? 0), 2),
            'total_records' => $progresos->count(),
        ]);
    }

    public function store(Request $request)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $validated = $request->validate([
            'id_rutina' => 'required|integer|exists:rutina,id_rutina',
            'id_ejercicio' => 'nullable|integer|exists:ejercicio,id_ejercicio',
            'fecha_realizacion' => 'nullable|date',
            'estado' => 'nullable|string|max:50',
            'porcentaje' => 'required|integer|min:0|max:100',
            'comentarios' => 'nullable|string|max:1000',
            'evidencia' => 'nullable|string',
            'desbloqueado' => 'nullable|boolean',
        ]);

        $id = DB::table('progreso')->insertGetId([
            'id_rutina' => $validated['id_rutina'],
            'id_ejercicio' => $validated['id_ejercicio'] ?? null,
            'fecha_realizacion' => $validated['fecha_realizacion'] ?? now()->format('Y-m-d'),
            'estado' => $validated['estado'] ?? 'Registrado',
            'comentarios' => $validated['comentarios'] ?? null,
            'evidencia' => $validated['evidencia'] ?? null,
            'porcentaje' => $validated['porcentaje'],
            'desbloqueado' => (int) ($validated['desbloqueado'] ?? 0),
        ]);

        return $this->success(['id_progreso' => $id], 'Progreso registrado.', 201);
    }

    public function byRutina(Request $request, int $idRutina)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $progresos = DB::table('progreso')
            ->where('id_rutina', $idRutina)
            ->orderBy('fecha_realizacion', 'desc')
            ->get();

        return $this->success([
            'progresos' => $progresos,
            'average_progress' => round((float) ($progresos->avg('porcentaje') ?? 0), 2),
        ]);
    }

    private function progresosPaciente(int $idPaciente)
    {
        return DB::table('progreso as p')
            ->join('rutina as r', 'p.id_rutina', '=', 'r.id_rutina')
            ->join('expediente as e', 'r.id_expediente', '=', 'e.id_expediente')
            ->leftJoin('rutinadetalles as rd', 'r.id_rutina', '=', 'rd.id_rutina')
            ->leftJoin('video as v', 'rd.id_video', '=', 'v.id_video')
            ->where('e.id_usuario', $idPaciente)
            ->select('p.*', 'r.fecha_asignacion', 'v.titulo as video_titulo')
            ->orderBy('p.fecha_realizacion', 'desc')
            ->get();
    }

    private function ultimaRutinaPaciente(int $idPaciente): ?object
    {
        return DB::table('rutina as r')
            ->join('expediente as e', 'r.id_expediente', '=', 'e.id_expediente')
            ->leftJoin('rutinadetalles as rd', 'r.id_rutina', '=', 'rd.id_rutina')
            ->leftJoin('video as v', 'rd.id_video', '=', 'v.id_video')
            ->where('e.id_usuario', $idPaciente)
            ->select(
                'r.id_rutina',
                'r.fecha_asignacion',
                'rd.repeticiones',
                'rd.series',
                'rd.tiempo',
                'rd.observaciones',
                'v.titulo as video_titulo',
                'v.url as video_url'
            )
            ->orderBy('r.fecha_asignacion', 'desc')
            ->orderBy('r.id_rutina', 'desc')
            ->first();
    }
}
