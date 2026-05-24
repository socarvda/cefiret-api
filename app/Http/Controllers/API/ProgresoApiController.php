<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\NotificacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgresoApiController extends Controller
{
    public function index()
    {
        try {
            $pacientes = DB::table('usuario')
                ->select(
                    'id_usuario',
                    'nombre',
                    'apaterno',
                    'amaterno',
                    'correo',
                    'telefono',
                    'fecha_nac',
                    'id_tipo_usuario',
                    'activo'
                )
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
                ->select(
                    'id_usuario',
                    'nombre',
                    'apaterno',
                    'amaterno',
                    'correo',
                    'telefono',
                    'fecha_nac',
                    'id_tipo_usuario',
                    'activo'
                )
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
                ->orderBy('p.id_progreso', 'desc')
                ->get();

            $ultimoProgreso = $progresos->first();

            $averageProgress = $progresos->avg('porcentaje') ?? 0;

            return response()->json([
                'success' => true,
                'paciente' => $paciente,
                'rutina' => $rutina,
                'progresos' => $progresos,
                'ultimoProgreso' => $ultimoProgreso,
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

            $nuevoPorcentaje = (float) $request->porcentaje;
            $fechaHoy = now()->format('Y-m-d');

            $ultimoProgreso = DB::table('progreso')
                ->where('id_rutina', $request->id_rutina)
                ->orderBy('fecha_realizacion', 'desc')
                ->orderBy('id_progreso', 'desc')
                ->first();

            if ($ultimoProgreso) {
                $ultimoPorcentaje = (float) $ultimoProgreso->porcentaje;

                if ($ultimoPorcentaje >= 100) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Esta rutina ya tiene un avance del 100%. No se pueden registrar más avances.'
                    ], 409);
                }

                if ($nuevoPorcentaje <= $ultimoPorcentaje) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El nuevo avance debe ser mayor al último avance registrado (' . $ultimoPorcentaje . '%).'
                    ], 409);
                }
            }

            $progresoHoy = DB::table('progreso')
                ->where('id_rutina', $request->id_rutina)
                ->where('fecha_realizacion', $fechaHoy)
                ->orderBy('id_progreso', 'desc')
                ->first();

            if ($progresoHoy) {
                DB::table('progreso')
                    ->where('id_progreso', $progresoHoy->id_progreso)
                    ->update([
                        'estado' => $nuevoPorcentaje >= 100 ? 'Completado' : 'Registrado',
                        'comentarios' => $request->comentarios,
                        'porcentaje' => $nuevoPorcentaje,
                        'desbloqueado' => $nuevoPorcentaje >= 100 ? 1 : 0
                    ]);

                $idProgreso = $progresoHoy->id_progreso;
                $accion = 'actualizado';
            } else {
                $idProgreso = DB::table('progreso')->insertGetId([
                    'id_rutina' => $request->id_rutina,
                    'fecha_realizacion' => $fechaHoy,
                    'estado' => $nuevoPorcentaje >= 100 ? 'Completado' : 'Registrado',
                    'comentarios' => $request->comentarios,
                    'porcentaje' => $nuevoPorcentaje,
                    'desbloqueado' => $nuevoPorcentaje >= 100 ? 1 : 0
                ]);

                $accion = 'registrado';
            }

            $paciente = DB::table('rutina as r')
                ->join('expediente as e', 'r.id_expediente', '=', 'e.id_expediente')
                ->join('usuario as u', 'e.id_usuario', '=', 'u.id_usuario')
                ->where('r.id_rutina', $request->id_rutina)
                ->select('u.id_usuario', 'u.nombre', 'u.apaterno')
                ->first();

            if ($paciente) {
                NotificacionService::crear(
                    (int) $paciente->id_usuario,
                    'Se ' . $accion . ' un avance de ' . $nuevoPorcentaje . '% en tu rutina.'
                );
            }

            return response()->json([
                'success' => true,
                'message' => $accion === 'actualizado'
                    ? 'Progreso de hoy actualizado correctamente.'
                    : 'Progreso registrado correctamente.',
                'id_progreso' => $idProgreso,
                'accion' => $accion
            ], $accion === 'actualizado' ? 200 : 201);
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
                ->select(
                    'id_usuario',
                    'nombre',
                    'apaterno',
                    'amaterno',
                    'correo',
                    'telefono',
                    'fecha_nac',
                    'id_tipo_usuario',
                    'activo'
                )
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
                ->orderBy('p.id_progreso', 'desc')
                ->get();

            $ultimoProgreso = $progresos->first();

            return response()->json([
                'success' => true,
                'paciente' => $paciente,
                'progresos' => $progresos,
                'ultimoProgreso' => $ultimoProgreso,
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