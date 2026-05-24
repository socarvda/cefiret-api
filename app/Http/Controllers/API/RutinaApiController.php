<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RutinaApiController extends Controller
{
    public function index()
    {
        try {
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
                    DB::raw('COALESCE(v.titulo, "") as video_titulo'),
                    DB::raw('COALESCE(v.url, "") as video_url')
                )
                ->orderBy('r.fecha_asignacion', 'desc')
                ->get();

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
                'rutinas' => $rutinas,
                'pacientes' => $pacientes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar las rutinas: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_paciente' => 'required|integer',
            'fecha_asignacion' => 'required|date',
            'video_titulo' => 'required|string',
            'video_url' => 'required|string',
            'repeticiones' => 'nullable|integer',
            'series' => 'nullable|integer',
            'tiempo' => 'nullable|integer',
            'observaciones' => 'nullable|string',
            'dias' => 'required|array|min:1'
        ]);

        try {
            DB::beginTransaction();

            $expediente = DB::table('expediente')
                ->where('id_usuario', $request->id_paciente)
                ->first();

            if (!$expediente) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'El paciente debe tener expediente antes de asignarle una rutina.'
                ], 409);
            }

            $expedienteId = $expediente->id_expediente;

            $videoId = DB::table('video')->insertGetId([
                'titulo' => $request->video_titulo,
                'descripcion' => $request->video_descripcion ?? '',
                'url' => $request->video_url
            ]);

            $rutinaId = DB::table('rutina')->insertGetId([
                'fecha_asignacion' => $request->fecha_asignacion,
                'id_expediente' => $expedienteId
            ]);

            DB::table('rutinadetalles')->insert([
                'id_rutina' => $rutinaId,
                'id_video' => $videoId,
                'repeticiones' => $request->repeticiones,
                'series' => $request->series,
                'tiempo' => $request->tiempo,
                'observaciones' => $request->observaciones
            ]);

            foreach ($request->dias as $dia) {
                DB::table('rutina_dias')->insert([
                    'id_rutina' => $rutinaId,
                    'dia' => $dia
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Rutina creada correctamente.',
                'id_rutina' => $rutinaId
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la rutina: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $rutina = DB::table('rutina as r')
                ->join('expediente as e', 'r.id_expediente', '=', 'e.id_expediente')
                ->join('usuario as u', 'e.id_usuario', '=', 'u.id_usuario')
                ->leftJoin('rutinadetalles as rd', 'r.id_rutina', '=', 'rd.id_rutina')
                ->leftJoin('video as v', 'rd.id_video', '=', 'v.id_video')
                ->where('r.id_rutina', $id)
                ->select(
                    'r.*',
                    'u.id_usuario as paciente_id',
                    'u.nombre',
                    'u.apaterno',
                    'u.amaterno',
                    'v.id_video',
                    'v.titulo',
                    'v.descripcion',
                    'v.url',
                    'rd.id_detalle',
                    'rd.repeticiones',
                    'rd.series',
                    'rd.tiempo',
                    'rd.observaciones'
                )
                ->first();

            if (!$rutina) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rutina no encontrada.'
                ], 404);
            }

            $dias = DB::table('rutina_dias')
                ->where('id_rutina', $id)
                ->pluck('dia')
                ->toArray();

            return response()->json([
                'success' => true,
                'rutina' => $rutina,
                'dias' => $dias
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar la rutina: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'video_titulo' => 'required|string',
            'video_url' => 'required|string',
            'repeticiones' => 'nullable|integer',
            'series' => 'nullable|integer',
            'tiempo' => 'nullable|integer',
            'observaciones' => 'nullable|string',
            'dias' => 'required|array|min:1'
        ]);

        try {
            DB::beginTransaction();

            $detalle = DB::table('rutinadetalles')
                ->where('id_rutina', $id)
                ->first();

            if (!$detalle) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'No existen detalles para esta rutina.'
                ], 404);
            }

            DB::table('video')
                ->where('id_video', $detalle->id_video)
                ->update([
                    'titulo' => $request->video_titulo,
                    'descripcion' => $request->video_descripcion ?? '',
                    'url' => $request->video_url
                ]);

            DB::table('rutinadetalles')
                ->where('id_rutina', $id)
                ->update([
                    'repeticiones' => $request->repeticiones,
                    'series' => $request->series,
                    'tiempo' => $request->tiempo,
                    'observaciones' => $request->observaciones
                ]);

            DB::table('rutina_dias')
                ->where('id_rutina', $id)
                ->delete();

            foreach ($request->dias as $dia) {
                DB::table('rutina_dias')->insert([
                    'id_rutina' => $id,
                    'dia' => $dia
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Rutina actualizada correctamente.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la rutina: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            DB::table('rutina_dias')->where('id_rutina', $id)->delete();
            DB::table('rutinadetalles')->where('id_rutina', $id)->delete();
            DB::table('progreso')->where('id_rutina', $id)->delete();
            DB::table('rutina')->where('id_rutina', $id)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Rutina eliminada correctamente.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la rutina: ' . $e->getMessage()
            ], 500);
        }
    }

    public function asignarExistente(Request $request)
    {
        $request->validate([
            'id_paciente' => 'required|integer',
            'rutina_existente' => 'required|integer'
        ]);

        try {
            DB::beginTransaction();

            $expediente = DB::table('expediente')
                ->where('id_usuario', $request->id_paciente)
                ->first();

            if (!$expediente) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'El paciente debe tener expediente antes de asignarle una rutina.'
                ], 409);
            }

            $original = DB::table('rutina')
                ->where('id_rutina', $request->rutina_existente)
                ->first();

            if (!$original) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'La rutina original no existe.'
                ], 404);
            }

            $detalle = DB::table('rutinadetalles')
                ->where('id_rutina', $request->rutina_existente)
                ->first();

            if (!$detalle) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'La rutina original no tiene detalles.'
                ], 404);
            }

            $video = DB::table('video')
                ->where('id_video', $detalle->id_video)
                ->first();

            if (!$video) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'La rutina original no tiene video.'
                ], 404);
            }

            $dias = DB::table('rutina_dias')
                ->where('id_rutina', $request->rutina_existente)
                ->pluck('dia');

            $videoId = DB::table('video')->insertGetId([
                'titulo' => $video->titulo,
                'descripcion' => $video->descripcion,
                'url' => $video->url
            ]);

            $newRutinaId = DB::table('rutina')->insertGetId([
                'fecha_asignacion' => now()->format('Y-m-d'),
                'id_expediente' => $expediente->id_expediente
            ]);

            DB::table('rutinadetalles')->insert([
                'id_rutina' => $newRutinaId,
                'id_video' => $videoId,
                'repeticiones' => $detalle->repeticiones,
                'series' => $detalle->series,
                'tiempo' => $detalle->tiempo,
                'observaciones' => $detalle->observaciones
            ]);

            foreach ($dias as $dia) {
                DB::table('rutina_dias')->insert([
                    'id_rutina' => $newRutinaId,
                    'dia' => $dia
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Rutina asignada correctamente.',
                'id_rutina' => $newRutinaId
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al asignar rutina: ' . $e->getMessage()
            ], 500);
        }
    }
}