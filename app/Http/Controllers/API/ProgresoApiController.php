<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\NotificacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgresoApiController extends Controller
{
    private function puedeConsultarPaciente(Request $request, int $idPaciente): bool
    {
        $usuario = $request->attributes->get('auth_user');

        if (!$usuario) {
            return false;
        }

        if (in_array((int) $usuario->id_tipo_usuario, [1, 2], true)) {
            return true;
        }

        return (int) $usuario->id_tipo_usuario === 3 &&
            (int) $usuario->id_usuario === (int) $idPaciente;
    }

    private function respuestaSinPermiso()
    {
        return response()->json([
            'success' => false,
            'message' => 'No tienes permisos para consultar el progreso de este paciente.'
        ], 403);
    }

    private function usuarioAutenticado(Request $request)
    {
        return $request->attributes->get('auth_user');
    }

    private function esAdminOFisio($usuario): bool
    {
        return in_array((int) ($usuario->id_tipo_usuario ?? 0), [1, 2], true);
    }

    private function esPaciente($usuario): bool
    {
        return (int) ($usuario->id_tipo_usuario ?? 0) === 3;
    }

    private function obtenerRutinaConPaciente($idRutina)
    {
        return DB::table('rutina as r')
            ->join('expediente as e', 'r.id_expediente', '=', 'e.id_expediente')
            ->join('usuario as u', 'e.id_usuario', '=', 'u.id_usuario')
            ->where('r.id_rutina', $idRutina)
            ->select(
                'r.id_rutina',
                'r.fecha_asignacion',
                'r.id_expediente',
                'e.id_usuario as id_paciente',
                'u.nombre',
                'u.apaterno',
                'u.amaterno',
                'u.correo'
            )
            ->first();
    }

    private function resolverIdEjercicio($idEjercicio)
    {
        if (!$idEjercicio) {
            return null;
        }

        $existe = DB::table('ejercicio')
            ->where('id_ejercicio', $idEjercicio)
            ->exists();

        return $existe ? (int) $idEjercicio : null;
    }

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

    public function show(Request $request, $idPaciente)
    {
        if (!$this->puedeConsultarPaciente($request, (int) $idPaciente)) {
            return $this->respuestaSinPermiso();
        }

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
                ->select(
                    'p.id_progreso',
                    'p.id_rutina',
                    'p.id_ejercicio',
                    'p.fecha_realizacion',
                    'p.estado',
                    'p.comentarios',
                    'p.evidencia',
                    'p.porcentaje',
                    'p.desbloqueado',
                    'r.fecha_asignacion'
                )
                ->orderBy('p.fecha_realizacion', 'desc')
                ->orderBy('p.id_progreso', 'desc')
                ->get();

            $ultimoProgreso = $progresos->first();

            return response()->json([
                'success' => true,
                'paciente' => $paciente,
                'rutina' => $rutina,
                'progresos' => $progresos,
                'ultimoProgreso' => $ultimoProgreso,
                'averageProgress' => $progresos->avg('porcentaje') ?? 0,
                'totalRecords' => $progresos->count()
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
        $usuarioAuth = $this->usuarioAutenticado($request);

        if (!$usuarioAuth) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado.'
            ], 401);
        }

        $request->validate([
            'id_rutina' => 'required|integer',
            'id_ejercicio' => 'nullable|integer',
            'porcentaje' => 'required|numeric|min:0|max:100',
            'estado' => 'nullable|string|max:50',
            'comentarios' => 'nullable|string|max:1000',
            'comentario' => 'nullable|string|max:1000',
            'observaciones' => 'nullable|string|max:1000',
            'evidencia' => 'nullable|string|max:1000'
        ], [
            'id_rutina.required' => 'La rutina es obligatoria.',
            'porcentaje.required' => 'El porcentaje es obligatorio.',
            'porcentaje.min' => 'El porcentaje no puede ser menor a 0.',
            'porcentaje.max' => 'El porcentaje no puede ser mayor a 100.'
        ]);

        try {
            $rutina = $this->obtenerRutinaConPaciente($request->id_rutina);

            if (!$rutina) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rutina no encontrada.'
                ], 404);
            }

            /*
             * Admin/fisio pueden registrar progreso de cualquier paciente.
             * Paciente solo puede registrar progreso de sus propias rutinas.
             */
            if ($this->esPaciente($usuarioAuth)) {
                if ((int) $usuarioAuth->id_usuario !== (int) $rutina->id_paciente) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No puedes registrar progreso de otra persona.'
                    ], 403);
                }
            } elseif (!$this->esAdminOFisio($usuarioAuth)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para registrar progreso.'
                ], 403);
            }

            $nuevoPorcentaje = (int) $request->porcentaje;

            /*
             * Se valida contra el último progreso de la rutina completa.
             * Así se conserva la regla que ya tenías:
             * el nuevo porcentaje debe ser mayor al anterior.
             */
            $ultimoProgreso = DB::table('progreso')
                ->where('id_rutina', $request->id_rutina)
                ->orderBy('fecha_realizacion', 'desc')
                ->orderBy('id_progreso', 'desc')
                ->first();

            if ($ultimoProgreso) {
                $ultimoPorcentaje = (int) $ultimoProgreso->porcentaje;

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

            $comentarios = $request->comentarios
                ?? $request->comentario
                ?? $request->observaciones
                ?? null;

            $idEjercicio = $this->resolverIdEjercicio($request->id_ejercicio);

            $estado = $request->estado;

            if (!$estado) {
                $estado = $nuevoPorcentaje >= 100 ? 'Completado' : 'Registrado';
            }

            /*
             * IMPORTANTE:
             * Aquí siempre insertamos un nuevo registro.
             * Ya no actualizamos el progreso del mismo día.
             */
            $idProgreso = DB::table('progreso')->insertGetId([
                'id_rutina' => (int) $request->id_rutina,
                'id_ejercicio' => $idEjercicio,
                'fecha_realizacion' => now()->format('Y-m-d'),
                'estado' => $estado,
                'comentarios' => $comentarios,
                'evidencia' => $request->evidencia,
                'porcentaje' => $nuevoPorcentaje,
                'desbloqueado' => $nuevoPorcentaje >= 100 ? 1 : 0
            ]);

            NotificacionService::crear(
                (int) $rutina->id_paciente,
                'Se registró un avance de ' . $nuevoPorcentaje . '% en tu rutina.'
            );

            return response()->json([
                'success' => true,
                'message' => 'Progreso registrado correctamente.',
                'id_progreso' => $idProgreso,
                'accion' => 'registrado',
                'progreso' => [
                    'id_progreso' => $idProgreso,
                    'id_rutina' => (int) $request->id_rutina,
                    'id_ejercicio' => $idEjercicio,
                    'fecha_realizacion' => now()->format('Y-m-d'),
                    'estado' => $estado,
                    'comentarios' => $comentarios,
                    'evidencia' => $request->evidencia,
                    'porcentaje' => $nuevoPorcentaje,
                    'desbloqueado' => $nuevoPorcentaje >= 100 ? 1 : 0
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar progreso: ' . $e->getMessage()
            ], 500);
        }
    }

    public function report(Request $request, $idPaciente)
    {
        if (!$this->puedeConsultarPaciente($request, (int) $idPaciente)) {
            return $this->respuestaSinPermiso();
        }

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
                    'p.id_progreso',
                    'p.id_rutina',
                    'p.id_ejercicio',
                    'p.fecha_realizacion',
                    'p.estado',
                    'p.comentarios',
                    'p.evidencia',
                    'p.porcentaje',
                    'p.desbloqueado',
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