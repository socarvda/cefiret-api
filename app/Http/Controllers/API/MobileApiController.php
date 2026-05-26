<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileApiController extends Controller
{
    private function puedeConsultarPaciente(Request $request, int $idPaciente)
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
            'message' => 'No tienes permisos para consultar información de este paciente.'
        ], 403);
    }

    public function videosPaciente(Request $request, $id)
    {
        if (!$this->puedeConsultarPaciente($request, (int) $id)) {
            return $this->respuestaSinPermiso();
        }

        try {
            $videos = DB::table('video_paciente as vp')
                ->join('video as v', 'vp.id_video', '=', 'v.id_video')
                ->where('vp.id_usuario', $id)
                ->select(
                    'vp.id_vp',
                    'vp.id_usuario',
                    'vp.id_video',
                    'vp.fecha',
                    'v.titulo',
                    'v.descripcion',
                    'v.url'
                )
                ->orderBy('vp.fecha', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'videos' => $videos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar videos: ' . $e->getMessage()
            ], 500);
        }
    }

    public function citasPaciente(Request $request, $id)
    {
        if (!$this->puedeConsultarPaciente($request, (int) $id)) {
            return $this->respuestaSinPermiso();
        }

        try {
            $citas = DB::table('cita')
                ->leftJoin('usuario as fisio', 'cita.id_fisioterapeuta', '=', 'fisio.id_usuario')
                ->where('cita.id_usuario', $id)
                ->select(
                    'cita.*',
                    'fisio.nombre as fisio_nombre',
                    'fisio.apaterno as fisio_apaterno',
                    'fisio.amaterno as fisio_amaterno'
                )
                ->orderBy('cita.fecha', 'desc')
                ->orderBy('cita.hora', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'citas' => $citas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar citas: ' . $e->getMessage()
            ], 500);
        }
    }

    public function pagosPaciente(Request $request, $id)
    {
        if (!$this->puedeConsultarPaciente($request, (int) $id)) {
            return $this->respuestaSinPermiso();
        }

        try {
            $pagos = DB::table('pago')
                ->where('id_usuario', $id)
                ->select(
                    'id_pago',
                    'id_usuario',
                    'monto',
                    'fecha_pago',
                    'metodo_pago',
                    'detalle'
                )
                ->orderBy('fecha_pago', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'pagos' => $pagos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar pagos: ' . $e->getMessage()
            ], 500);
        }
    }
}