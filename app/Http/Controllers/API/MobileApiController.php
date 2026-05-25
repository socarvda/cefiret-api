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
            /*
             * Antes consultaba video_paciente, pero tus rutinas reales están en:
             * rutina -> expediente -> usuario
             * rutina -> rutinadetalles -> video
             */
            $videos = DB::table('rutina as r')
                ->join('expediente as e', 'r.id_expediente', '=', 'e.id_expediente')
                ->join('usuario as u', 'e.id_usuario', '=', 'u.id_usuario')
                ->leftJoin('rutinadetalles as rd', 'r.id_rutina', '=', 'rd.id_rutina')
                ->leftJoin('video as v', 'rd.id_video', '=', 'v.id_video')
                ->where('e.id_usuario', $id)
                ->select(
                    'r.id_rutina',
                    'e.id_usuario',
                    'r.fecha_asignacion as fecha',
                    'v.id_video',
                    'v.titulo',
                    'v.descripcion',
                    'v.url',
                    'rd.repeticiones',
                    'rd.series',
                    'rd.tiempo',
                    'rd.observaciones'
                )
                ->orderBy('r.fecha_asignacion', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'videos' => $videos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar rutinas del paciente: ' . $e->getMessage()
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
                    'cita.id_cita',
                    'cita.fecha',
                    'cita.hora',
                    'cita.motivo',
                    'cita.observaciones',
                    'cita.estatus',
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
                ->get()
                ->map(function ($pago) {
                    $detalle = (string) ($pago->detalle ?? '');

                    preg_match('/Sesión Stripe:\s*(cs_[^\s|]+)/i', $detalle, $match);

                    $pago->stripe_transaction_id = $match[1] ?? null;

                    return $pago;
                });

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