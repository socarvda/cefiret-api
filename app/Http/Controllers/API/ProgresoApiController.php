<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgresoApiController extends Controller
{
    public function index()
    {
        try {
            $pacientes = DB::table('usuario')
                ->where('id_tipo_usuario', 3)
                ->orderBy('nombre')
                ->get();

            return response()->json([
                'success' => true,
                'pacientes' => $pacientes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar pacientes: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($idPaciente)
    {
        try {
            $paciente = DB::table('usuario')
                ->where('id_usuario', $idPaciente)
                ->first();

            if (!$paciente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paciente no encontrado.'
                ], 404);
            }

            $rutina = DB::table('rutina as r')
                ->join('expediente as e', 'r.id_expediente', '=', 'e.id_expediente')
                ->leftJoin('rutinadetalles as rd', 'r.id_rutina', '=', 'rd.id_rutina')
                ->leftJoin('video as v', 'rd.id_video', '=', 'v.id_video')
                ->where('e.id_usuario', $idPaciente)
                ->select(
                    'r.id_rutina',
                    'r.fecha_asignacion',
                    'v.titulo as video_titulo',
                    'v.url as video_url',
                    'rd.repeticiones',
                    'rd.series',
                    'rd.tiempo',
                    'rd.observaciones'
                )
                ->orderBy('r.fecha_asignacion', 'desc')
                ->first();

            $progresos = DB::table('progreso as p')
                ->join('rutina as r', 'p.id_rutina', '=', 'r.id_rutina')
                ->join('expediente as e', 'r.id_expediente', '=', 'e.id_expediente')
                ->where('e.id_usuario', $idPaciente)
                ->select('p.*', 'r.fecha_asignacion')
                ->orderBy('p.fecha_realizacion', 'desc')
                ->get();

            $averageProgress = $progresos->avg('porcentaje') ?? 0;

            return response()->json([
                'success' => true,
                'paciente' => $paciente,
                'rutina' => $rutina,
                'progresos' => $progresos,
                'averageProgress' => $averageProgress
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar el progreso: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_rutina' => 'required|integer',
            'porcentaje' => 'required|numeric|min:0|max:100',
            'comentarios' => 'nullable|string|max:1000'
        ]);

        try {
            $rutina = DB::table('rutina')
                ->where('id_rutina', $request->id_rutina)
                ->first();

            if (!$rutina) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rutina no encontrada.'
                ], 404);
            }

            $idProgreso = DB::table('progreso')->insertGetId([
                'id_rutina' => $request->id_rutina,
                'fecha_realizacion' => now()->format('Y-m-d'),
                'estado' => 'Registrado',
                'comentarios' => $request->comentarios,
                'porcentaje' => $request->porcentaje,
                'desbloqueado' => 0
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Progreso registrado correctamente.',
                'id_progreso' => $idProgreso
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar progreso: ' . $e->getMessage()
            ], 500);
        }
    }

    public function report($idPaciente)
    {
        try {
            $paciente = DB::table('usuario')
                ->where('id_usuario', $idPaciente)
                ->first();

            if (!$paciente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paciente no encontrado.'
                ], 404);
            }

            $progresos = DB::table('progreso as p')
                ->join('rutina as r', 'p.id_rutina', '=', 'r.id_rutina')
                ->join('expediente as e', 'r.id_expediente', '=', 'e.id_expediente')
                ->leftJoin('rutinadetalles as rd', 'r.id_rutina', '=', 'rd.id_rutina')
                ->leftJoin('video as v', 'rd.id_video', '=', 'v.id_video')
                ->where('e.id_usuario', $idPaciente)
                ->select(
                    'p.*',
                    'r.fecha_asignacion',
                    'v.titulo as video_titulo',
                    'rd.repeticiones',
                    'rd.series',
                    'rd.tiempo',
                    'rd.observaciones'
                )
                ->orderBy('p.fecha_realizacion', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'paciente' => $paciente,
                'progresos' => $progresos,
                'averageProgress' => $progresos->avg('porcentaje') ?? 0,
                'totalRecords' => $progresos->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte: ' . $e->getMessage()
            ], 500);
        }
    }
}