<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class HashPlainTextUsuarioPasswords extends Command
{
    protected $signature = 'cefiret:hash-usuario-passwords {--dry-run}';

    protected $description = 'Hashea contraseñas antiguas en texto plano de la tabla usuario';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $usuarios = DB::table('usuario')
            ->select('id_usuario', 'correo', 'contrasena')
            ->get();

        $total = 0;

        foreach ($usuarios as $usuario) {
            $contrasena = (string) $usuario->contrasena;

            $yaEstaHasheada = strlen($contrasena) === 60 &&
                (
                    str_starts_with($contrasena, '$2y$') ||
                    str_starts_with($contrasena, '$2a$') ||
                    str_starts_with($contrasena, '$2b$')
                );

            if ($yaEstaHasheada) {
                continue;
            }

            $total++;

            $this->line("Usuario {$usuario->id_usuario} ({$usuario->correo}) tiene contraseña en texto plano.");

            if (!$dryRun) {
                DB::table('usuario')
                    ->where('id_usuario', $usuario->id_usuario)
                    ->update([
                        'contrasena' => Hash::make($contrasena)
                    ]);
            }
        }

        if ($dryRun) {
            $this->info("Revisión terminada. Se encontraron {$total} contraseña(s) en texto plano. No se modificó nada.");
        } else {
            $this->info("Proceso terminado. Se hashearon {$total} contraseña(s).");
        }

        return self::SUCCESS;
    }
}