<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HistorialVisitaApiController extends Controller
{
    private function authUser(Request $request)
    {
        return $request->attributes->get('auth_user');
    }

    private function esAdminOFisio($usuario): bool
    {
        return in_array((int) ($usuario->id_tipo_usuario ?? 0), [1, 2], true);
    }

    private function puedeConsultarPaciente(Request $request, int $idPaciente): bool
    {
        $usuario = $this->authUser($request);

        if (!$usuario) {
            return false;
        }

        if ($this->esAdminOFisio($usuario)) {
            return true;
        }

        return (int) $usuario->id_tipo_usuario === 3 &&
            (int) $usuario->id_usuario === $idPaciente;
    }

    public function index(Request $request, $idPaciente)
    {
        $idPaciente = (int) $idPaciente;

        if (!$this->puedeConsultarPaciente($request, $idPaciente)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para consultar este historial.'
            ], 403);
        }

        try {
            $historial = DB::table('historial_visita as hv')
                ->leftJoin('usuario as u', 'hv.registrado_por', '=', 'u.id_usuario')
                ->where('hv.id_usuario', $idPaciente)
                ->select(
                    'hv.id_historial',
                    'hv.id_usuario',
                    'hv.fecha_visita',
                    'hv.motivo',
                    'hv.tratamiento_realizado',
                    'hv.observaciones',
                    'u.nombre as registrado_nombre',
                    'u.apaterno as registrado_apaterno',
                    'u.amaterno as registrado_amaterno'
                )
                ->orderByDesc('hv.fecha_visita')
                ->orderByDesc('hv.id_historial')
                ->get();

            return response()->json([
                'success' => true,
                'historial' => $historial
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar historial de visitas: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request, $idPaciente)
    {
        $usuario = $this->authUser($request);

        if (!$this->esAdminOFisio($usuario)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para registrar visitas.'
            ], 403);
        }

        $idPaciente = (int) $idPaciente;

        $pacienteExiste = DB::table('usuario')
            ->where('id_usuario', $idPaciente)
            ->where('id_tipo_usuario', 3)
            ->exists();

        if (!$pacienteExiste) {
            return response()->json([
                'success' => false,
                'message' => 'Paciente no encontrado.'
            ], 404);
        }

        $request->validate([
            'fecha_visita' => 'required|date',
            'motivo' => 'required|string|max:255',
            'tratamiento_realizado' => 'required|string',
            'observaciones' => 'nullable|string',
        ], [
            'fecha_visita.required' => 'La fecha de visita es obligatoria.',
            'motivo.required' => 'El motivo es obligatorio.',
            'tratamiento_realizado.required' => 'El tratamiento realizado es obligatorio.',
        ]);

        try {
            DB::table('historial_visita')->insert([
                'id_usuario' => $idPaciente,
                'fecha_visita' => $request->fecha_visita,
                'motivo' => $request->motivo,
                'tratamiento_realizado' => $request->tratamiento_realizado,
                'observaciones' => $request->observaciones,
                'registrado_por' => $usuario->id_usuario,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Visita registrada correctamente.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar visita: ' . $e->getMessage()
            ], 500);
        }
    }
}