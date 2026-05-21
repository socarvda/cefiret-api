<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpedienteApiController extends ApiController
{
    public function pacientes(Request $request)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $query = trim((string) $request->get('q', ''));

        $pacientes = DB::table('usuario')
            ->select('id_usuario', 'nombre', 'apaterno', 'amaterno', 'correo', 'telefono', 'fecha_nac', 'activo')
            ->where('id_tipo_usuario', 3)
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($q) use ($query) {
                    $q->where('nombre', 'like', "%{$query}%")
                        ->orWhere('apaterno', 'like', "%{$query}%")
                        ->orWhere('amaterno', 'like', "%{$query}%")
                        ->orWhere('correo', 'like', "%{$query}%")
                        ->orWhere('telefono', 'like', "%{$query}%");
                });
            })
            ->orderBy('nombre')
            ->get();

        return $this->success($pacientes);
    }

    public function show(Request $request, int $idUsuario)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $paciente = DB::table('usuario')
            ->select('id_usuario', 'nombre', 'apaterno', 'amaterno', 'correo', 'telefono', 'fecha_nac', 'activo')
            ->where('id_usuario', $idUsuario)
            ->where('id_tipo_usuario', 3)
            ->first();

        if (!$paciente) {
            return $this->error('Paciente no encontrado.', 404);
        }

        $expediente = DB::table('expediente')->where('id_usuario', $idUsuario)->first();
        $habitos = $expediente
            ? DB::table('habitos_higien')->where('id_expediente', $expediente->id_expediente)->first()
            : null;
        $vivienda = $expediente
            ? DB::table('vivienda')->where('id_expediente', $expediente->id_expediente)->first()
            : null;
        $citas = DB::table('cita')
            ->leftJoin('usuario as fisio', 'cita.id_fisioterapeuta', '=', 'fisio.id_usuario')
            ->where('cita.id_usuario', $idUsuario)
            ->select('cita.*', 'fisio.nombre as fisio_nombre', 'fisio.apaterno as fisio_apaterno')
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->get();
        $rutinas = DB::table('rutina as r')
            ->join('expediente as e', 'r.id_expediente', '=', 'e.id_expediente')
            ->leftJoin('rutinadetalles as rd', 'r.id_rutina', '=', 'rd.id_rutina')
            ->leftJoin('video as v', 'rd.id_video', '=', 'v.id_video')
            ->where('e.id_usuario', $idUsuario)
            ->select('r.*', 'rd.repeticiones', 'rd.series', 'rd.tiempo', 'rd.observaciones', 'v.titulo as video_titulo', 'v.url as video_url')
            ->orderBy('r.fecha_asignacion', 'desc')
            ->get();

        return $this->success(compact('paciente', 'expediente', 'habitos', 'vivienda', 'citas', 'rutinas'));
    }

    public function store(Request $request, int $idUsuario)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $validated = $request->validate([
            'fecha_creacion' => 'required|date',
            'sexo' => 'required|string|max:10',
            'edad' => 'required|integer|min:0',
            'edo_civil' => 'required|string|max:30',
            'ocupacion' => 'required|string|max:50',
            'domicilio' => 'required|string|max:100',
            'colonia' => 'required|string|max:50',
            'municipio' => 'required|string|max:50',
            'lugar_nac' => 'required|string|max:50',
            'lugar_residencia' => 'required|string|max:50',
            'contacto_emergencia' => 'required|string|max:100',
            'nacionalidad' => 'required|string|max:50',
            'religion' => 'nullable|string|max:50',
            'escolaridad' => 'nullable|string|max:50',
            'presion_arterial' => 'nullable|string|max:20',
            'frec_cardiaca' => 'nullable|string|max:20',
            'llenado_capilar' => 'nullable|string|max:20',
            'glucosa' => 'nullable|string|max:20',
            'frec_respiratoria' => 'nullable|string|max:20',
            'alimentacion' => 'nullable|string|max:100',
            'bano' => 'nullable|string|max:30',
            'lavado_manos' => 'nullable|string|max:100',
            'lavado_dientes' => 'nullable|string|max:100',
            'cambio_ropa' => 'nullable|string|max:100',
            'revision_pies' => 'nullable|string|max:100',
            'horas_sueno' => 'nullable|string|max:50',
            'vivienda_detalles' => 'nullable|string',
            'techo' => 'nullable|string|max:50',
            'paredes' => 'nullable|string|max:50',
            'suelo' => 'nullable|string|max:50',
            'agua' => 'nullable|string|max:100',
            'luz' => 'nullable|string|max:100',
            'drenaje' => 'nullable|string|max:100',
            'gas' => 'nullable|string|max:100',
            'limpieza_hogar' => 'nullable|string|max:100',
        ]);

        $pacienteExiste = DB::table('usuario')
            ->where('id_usuario', $idUsuario)
            ->where('id_tipo_usuario', 3)
            ->exists();

        if (!$pacienteExiste) {
            return $this->error('Paciente no encontrado.', 404);
        }

        $idExpediente = DB::transaction(function () use ($validated, $idUsuario) {
            $expedienteData = [
                'id_usuario' => $idUsuario,
                'fecha_creacion' => $validated['fecha_creacion'],
                'sexo' => $validated['sexo'],
                'edad' => $validated['edad'],
                'edo_civil' => $validated['edo_civil'],
                'ocupacion' => $validated['ocupacion'],
                'domicilio' => $validated['domicilio'],
                'colonia' => $validated['colonia'],
                'municipio' => $validated['municipio'],
                'lugar_nac' => $validated['lugar_nac'],
                'lugar_residencia' => $validated['lugar_residencia'],
                'contacto_emergencia' => $validated['contacto_emergencia'],
                'nacionalidad' => $validated['nacionalidad'],
                'religion' => $validated['religion'] ?? null,
                'escolaridad' => $validated['escolaridad'] ?? null,
                'presion_arterial' => $validated['presion_arterial'] ?? null,
                'frec_cardiaca' => $validated['frec_cardiaca'] ?? null,
                'llenado_capilar' => $validated['llenado_capilar'] ?? null,
                'glucosa' => $validated['glucosa'] ?? null,
                'frec_respiratoria' => $validated['frec_respiratoria'] ?? null,
                'alimentacion' => $validated['alimentacion'] ?? null,
            ];

            $existente = DB::table('expediente')->where('id_usuario', $idUsuario)->first();

            if ($existente) {
                DB::table('expediente')->where('id_expediente', $existente->id_expediente)->update($expedienteData);
                $idExpediente = $existente->id_expediente;
            } else {
                $idExpediente = DB::table('expediente')->insertGetId($expedienteData);
            }

            DB::table('habitos_higien')->updateOrInsert(
                ['id_expediente' => $idExpediente],
                [
                    'bano' => $validated['bano'] ?? null,
                    'lavado_manos' => $validated['lavado_manos'] ?? null,
                    'lavado_dientes' => $validated['lavado_dientes'] ?? null,
                    'cambio_ropa' => $validated['cambio_ropa'] ?? null,
                    'revision_pies' => $validated['revision_pies'] ?? null,
                    'horas_sueno' => $validated['horas_sueno'] ?? null,
                ]
            );

            DB::table('vivienda')->updateOrInsert(
                ['id_expediente' => $idExpediente],
                [
                    'detalles' => $validated['vivienda_detalles'] ?? null,
                    'techo' => $validated['techo'] ?? null,
                    'paredes' => $validated['paredes'] ?? null,
                    'suelo' => $validated['suelo'] ?? null,
                    'agua' => $validated['agua'] ?? null,
                    'luz' => $validated['luz'] ?? null,
                    'drenaje' => $validated['drenaje'] ?? null,
                    'gas' => $validated['gas'] ?? null,
                    'limpieza_hogar' => $validated['limpieza_hogar'] ?? null,
                ]
            );

            return $idExpediente;
        });

        return $this->success(['id_expediente' => $idExpediente], 'Expediente guardado.');
    }

    public function citas(Request $request, int $idUsuario)
    {
        if ($auth = $this->requireAuth($request)) {
            return $auth;
        }

        $citas = DB::table('cita')
            ->join('usuario as fisio', 'cita.id_fisioterapeuta', '=', 'fisio.id_usuario')
            ->where('cita.id_usuario', $idUsuario)
            ->select('cita.*', 'fisio.nombre as fisio_nombre', 'fisio.apaterno as fisio_apaterno')
            ->orderBy('cita.fecha', 'desc')
            ->orderBy('cita.hora', 'desc')
            ->get();

        return $this->success($citas);
    }
}
