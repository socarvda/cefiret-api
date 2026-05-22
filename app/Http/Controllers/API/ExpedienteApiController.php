<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpedienteApiController extends Controller
{
    public function pacientes(Request $request)
    {
        $query = $request->get('query');

        $pacientes = DB::table('usuario')
            ->where('id_tipo_usuario', 3)
            ->when($query, function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('nombre', 'like', "%$query%")
                        ->orWhere('apaterno', 'like', "%$query%")
                        ->orWhere('amaterno', 'like', "%$query%")
                        ->orWhere('correo', 'like', "%$query%")
                        ->orWhere('telefono', 'like', "%$query%");
                });
            })
            ->get();

        return response()->json(['success' => true, 'pacientes' => $pacientes]);
    }

    public function show($id)
    {
        $paciente = DB::table('usuario')->where('id_usuario', $id)->first();

        if (!$paciente) {
            return response()->json(['success' => false, 'message' => 'Paciente no encontrado'], 404);
        }

        $expediente = DB::table('expediente')->where('id_usuario', $id)->first();
        $habitos = null;
        $vivienda = null;

        if ($expediente) {
            $habitos = DB::table('habitos_higien')->where('id_expediente', $expediente->id_expediente)->first();
            $vivienda = DB::table('vivienda')->where('id_expediente', $expediente->id_expediente)->first();
        }

        return response()->json([
            'success' => true,
            'paciente' => $paciente,
            'expediente' => $expediente,
            'habitos' => $habitos,
            'vivienda' => $vivienda
        ]);
    }

    public function store(Request $request, $id)
    {
        $request->validate([
            'fecha_creacion' => 'required|date',
            'sexo' => 'required|string',
            'edad' => 'required|integer|min:0',
            'edo_civil' => 'required|string',
            'ocupacion' => 'required|string',
            'domicilio' => 'required|string',
            'colonia' => 'required|string',
            'municipio' => 'required|string',
            'lugar_nac' => 'required|string',
            'lugar_residencia' => 'required|string',
            'contacto_emergencia' => 'required|string',
            'nacionalidad' => 'required|string'
        ]);

        $expedienteExistente = DB::table('expediente')->where('id_usuario', $id)->first();

        if ($expedienteExistente) {
            return response()->json([
                'success' => false,
                'message' => 'Este paciente ya tiene expediente.'
            ], 409);
        }

        $idExpediente = DB::table('expediente')->insertGetId([
            'id_usuario' => $id,
            'fecha_creacion' => $request->fecha_creacion,
            'sexo' => $request->sexo,
            'edad' => $request->edad,
            'edo_civil' => $request->edo_civil,
            'ocupacion' => $request->ocupacion,
            'domicilio' => $request->domicilio,
            'colonia' => $request->colonia,
            'municipio' => $request->municipio,
            'lugar_nac' => $request->lugar_nac,
            'lugar_residencia' => $request->lugar_residencia,
            'contacto_emergencia' => $request->contacto_emergencia,
            'nacionalidad' => $request->nacionalidad,
            'religion' => $request->religion,
            'escolaridad' => $request->escolaridad,
            'presion_arterial' => substr($request->presion_arterial ?? '', 0, 50),
            'frec_cardiaca' => substr($request->frec_cardiaca ?? '', 0, 50),
            'llenado_capilar' => substr($request->llenado_capilar ?? '', 0, 50),
            'glucosa' => substr($request->glucosa ?? '', 0, 50),
            'frec_respiratoria' => substr($request->frec_respiratoria ?? '', 0, 50),
            'alimentacion' => substr($request->alimentacion ?? '', 0, 100)
        ]);

        DB::table('habitos_higien')->insert([
            'id_expediente' => $idExpediente,
            'bano' => substr($request->bano ?? '', 0, 100),
            'lavado_manos' => substr($request->lavado_manos ?? '', 0, 100),
            'lavado_dientes' => substr($request->lavado_dientes ?? '', 0, 100),
            'cambio_ropa' => substr($request->cambio_ropa ?? '', 0, 100),
            'revision_pies' => substr($request->revision_pies ?? '', 0, 100),
            'horas_sueno' => substr($request->horas_sueno ?? '', 0, 100)
        ]);

        DB::table('vivienda')->insert([
            'id_expediente' => $idExpediente,
            'detalles' => $request->vivienda_detalles,
            'techo' => substr($request->techo ?? '', 0, 100),
            'paredes' => substr($request->paredes ?? '', 0, 100),
            'suelo' => substr($request->suelo ?? '', 0, 100),
            'agua' => substr($request->agua ?? '', 0, 100),
            'luz' => substr($request->luz ?? '', 0, 100),
            'drenaje' => substr($request->drenaje ?? '', 0, 100),
            'gas' => substr($request->gas ?? '', 0, 100),
            'limpieza_hogar' => substr($request->limpieza_hogar ?? '', 0, 100)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expediente clínico completado exitosamente.',
            'id_expediente' => $idExpediente
        ], 201);
    }

    public function citas($id)
    {
        $citas = DB::table('cita')
            ->join('usuario as fisio', 'cita.id_fisioterapeuta', '=', 'fisio.id_usuario')
            ->where('cita.id_usuario', $id)
            ->select('cita.*', 'fisio.nombre as fisio_nombre', 'fisio.apaterno as fisio_apaterno')
            ->orderBy('cita.fecha', 'desc')
            ->get();

        return response()->json(['success' => true, 'citas' => $citas]);
    }
}