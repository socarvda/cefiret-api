<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RutinaApiController extends ApiController
{
    public function index(Request $request)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $rutinas = DB::table('rutina as r')
            ->join('expediente as e', 'r.id_expediente', '=', 'e.id_expediente')
            ->join('usuario as u', 'e.id_usuario', '=', 'u.id_usuario')
            ->leftJoin('rutinadetalles as rd', 'r.id_rutina', '=', 'rd.id_rutina')
            ->leftJoin('video as v', 'rd.id_video', '=', 'v.id_video')
            ->select(
                'r.id_rutina',
                'r.fecha_asignacion',
                'u.id_usuario as paciente_id',
                'u.nombre',
                'u.apaterno',
                'u.amaterno',
                'rd.repeticiones',
                'rd.series',
                'rd.tiempo',
                'rd.observaciones',
                'v.id_video',
                'v.titulo as video_titulo',
                'v.descripcion as video_descripcion',
                'v.url as video_url'
            )
            ->orderBy('r.fecha_asignacion', 'desc')
            ->get();

        return $this->success($rutinas);
    }

    public function store(Request $request)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $validated = $request->validate([
            'id_paciente' => 'required|integer|exists:usuario,id_usuario',
            'fecha_asignacion' => 'required|date',
            'video_titulo' => 'required|string|max:100',
            'video_url' => 'required|string',
            'video_descripcion' => 'nullable|string',
            'repeticiones' => 'nullable|integer|min:1',
            'series' => 'nullable|integer|min:1',
            'tiempo' => 'nullable|integer|min:1',
            'observaciones' => 'nullable|string',
            'dias' => 'required|array|min:1',
            'dias.*' => 'required|string|max:20',
        ]);

        $idRutina = DB::transaction(function () use ($validated) {
            $expediente = DB::table('expediente')->where('id_usuario', $validated['id_paciente'])->first();

            $idExpediente = $expediente
                ? $expediente->id_expediente
                : DB::table('expediente')->insertGetId([
                    'id_usuario' => $validated['id_paciente'],
                    'fecha_creacion' => now()->format('Y-m-d'),
                    'sexo' => 'No especificado',
                    'edad' => 0,
                    'edo_civil' => 'No especificado',
                    'ocupacion' => 'No especificado',
                    'alimentacion' => 'No especificado',
                ]);

            $idVideo = DB::table('video')->insertGetId([
                'titulo' => $validated['video_titulo'],
                'descripcion' => $validated['video_descripcion'] ?? '',
                'url' => $validated['video_url'],
            ]);

            $idRutina = DB::table('rutina')->insertGetId([
                'fecha_asignacion' => $validated['fecha_asignacion'],
                'id_expediente' => $idExpediente,
            ]);

            DB::table('rutinadetalles')->insert([
                'id_rutina' => $idRutina,
                'id_video' => $idVideo,
                'repeticiones' => $validated['repeticiones'] ?? null,
                'series' => $validated['series'] ?? null,
                'tiempo' => $validated['tiempo'] ?? null,
                'observaciones' => $validated['observaciones'] ?? null,
            ]);

            foreach ($validated['dias'] as $dia) {
                DB::table('rutina_dias')->insert([
                    'id_rutina' => $idRutina,
                    'dia' => $this->normalizarDia($dia),
                ]);
            }

            return $idRutina;
        });

        return $this->success(['id_rutina' => $idRutina], 'Rutina creada.', 201);
    }

    public function show(Request $request, int $id)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $rutina = $this->rutinaQuery()->where('r.id_rutina', $id)->first();

        if (!$rutina) {
            return $this->error('Rutina no encontrada.', 404);
        }

        $dias = DB::table('rutina_dias')->where('id_rutina', $id)->pluck('dia');
        $progresos = DB::table('progreso')->where('id_rutina', $id)->orderBy('fecha_realizacion', 'desc')->get();

        return $this->success([
            'rutina' => $rutina,
            'dias' => $dias,
            'progresos' => $progresos,
            'average_progress' => round((float) ($progresos->avg('porcentaje') ?? 0), 2),
        ]);
    }

    public function update(Request $request, int $id)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $validated = $request->validate([
            'fecha_asignacion' => 'nullable|date',
            'video_titulo' => 'required|string|max:100',
            'video_url' => 'required|string',
            'video_descripcion' => 'nullable|string',
            'repeticiones' => 'nullable|integer|min:1',
            'series' => 'nullable|integer|min:1',
            'tiempo' => 'nullable|integer|min:1',
            'observaciones' => 'nullable|string',
            'dias' => 'required|array|min:1',
            'dias.*' => 'required|string|max:20',
        ]);

        $detalle = DB::table('rutinadetalles')->where('id_rutina', $id)->first();
        if (!$detalle) {
            return $this->error('Rutina no encontrada o sin detalles.', 404);
        }

        DB::transaction(function () use ($validated, $id, $detalle) {
            if (!empty($validated['fecha_asignacion'])) {
                DB::table('rutina')->where('id_rutina', $id)->update([
                    'fecha_asignacion' => $validated['fecha_asignacion'],
                ]);
            }

            DB::table('video')->where('id_video', $detalle->id_video)->update([
                'titulo' => $validated['video_titulo'],
                'descripcion' => $validated['video_descripcion'] ?? '',
                'url' => $validated['video_url'],
            ]);

            DB::table('rutinadetalles')->where('id_rutina', $id)->update([
                'repeticiones' => $validated['repeticiones'] ?? null,
                'series' => $validated['series'] ?? null,
                'tiempo' => $validated['tiempo'] ?? null,
                'observaciones' => $validated['observaciones'] ?? null,
            ]);

            DB::table('rutina_dias')->where('id_rutina', $id)->delete();
            foreach ($validated['dias'] as $dia) {
                DB::table('rutina_dias')->insert([
                    'id_rutina' => $id,
                    'dia' => $this->normalizarDia($dia),
                ]);
            }
        });

        return $this->success(null, 'Rutina actualizada.');
    }

    public function destroy(Request $request, int $id)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        DB::transaction(function () use ($id) {
            DB::table('rutina_dias')->where('id_rutina', $id)->delete();
            DB::table('progreso')->where('id_rutina', $id)->delete();
            DB::table('rutinadetalles')->where('id_rutina', $id)->delete();
            DB::table('rutina')->where('id_rutina', $id)->delete();
        });

        return $this->success(null, 'Rutina eliminada.');
    }

    public function asignarExistente(Request $request)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $validated = $request->validate([
            'id_paciente' => 'required|integer|exists:usuario,id_usuario',
            'rutina_existente' => 'required|integer|exists:rutina,id_rutina',
        ]);

        $idRutina = DB::transaction(function () use ($validated) {
            $expediente = DB::table('expediente')->where('id_usuario', $validated['id_paciente'])->first();
            if (!$expediente) {
                throw new \RuntimeException('El paciente no tiene expediente.');
            }

            $original = DB::table('rutina')->where('id_rutina', $validated['rutina_existente'])->first();
            $detalle = DB::table('rutinadetalles')->where('id_rutina', $validated['rutina_existente'])->first();
            $video = DB::table('video')->where('id_video', $detalle->id_video)->first();
            $dias = DB::table('rutina_dias')->where('id_rutina', $validated['rutina_existente'])->pluck('dia');

            $idVideo = DB::table('video')->insertGetId([
                'titulo' => $video->titulo,
                'descripcion' => $video->descripcion,
                'url' => $video->url,
            ]);

            $idRutina = DB::table('rutina')->insertGetId([
                'fecha_asignacion' => now()->format('Y-m-d'),
                'id_expediente' => $expediente->id_expediente,
            ]);

            DB::table('rutinadetalles')->insert([
                'id_rutina' => $idRutina,
                'id_video' => $idVideo,
                'repeticiones' => $detalle->repeticiones,
                'series' => $detalle->series,
                'tiempo' => $detalle->tiempo,
                'observaciones' => $detalle->observaciones,
            ]);

            foreach ($dias as $dia) {
                DB::table('rutina_dias')->insert(['id_rutina' => $idRutina, 'dia' => $this->normalizarDia($dia)]);
            }

            return $idRutina;
        });

        return $this->success(['id_rutina' => $idRutina], 'Rutina asignada.');
    }

    private function rutinaQuery()
    {
        return DB::table('rutina as r')
            ->join('expediente as e', 'r.id_expediente', '=', 'e.id_expediente')
            ->join('usuario as u', 'e.id_usuario', '=', 'u.id_usuario')
            ->leftJoin('rutinadetalles as rd', 'r.id_rutina', '=', 'rd.id_rutina')
            ->leftJoin('video as v', 'rd.id_video', '=', 'v.id_video')
            ->select(
                'r.*',
                'u.id_usuario as paciente_id',
                'u.nombre',
                'u.apaterno',
                'u.amaterno',
                'rd.repeticiones',
                'rd.series',
                'rd.tiempo',
                'rd.observaciones',
                'v.id_video',
                'v.titulo',
                'v.descripcion',
                'v.url'
            );
    }

    private function normalizarDia(string $dia): string
    {
        $dia = trim(mb_strtolower($dia));

        return match ($dia) {
            'lunes' => 'Lunes',
            'martes' => 'Martes',
            'miercoles', 'miércoles', 'mi??rcoles' => 'Miercoles',
            'jueves' => 'Jueves',
            'viernes' => 'Viernes',
            'sabado', 'sábado', 's??bado' => 'Sabado',
            'domingo' => 'Domingo',
            default => ucfirst($dia),
        };
    }
}
