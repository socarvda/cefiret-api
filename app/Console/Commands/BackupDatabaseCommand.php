<?php

namespace App\Console\Commands;

use App\Mail\BackupDatabaseMailable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'cefiret:backup-db {--mail : Enviar el respaldo por correo}';

    protected $description = 'Genera un respaldo SQL de la base de datos de CEFIRET';

    public function handle(): int
    {
        try {
            $database = config('database.connections.mysql.database');
            $fileName = 'cefiret_backup_' . now()->format('Y_m_d_His') . '.sql';
            $relativePath = 'backups/' . $fileName;
            $absolutePath = storage_path('app/' . $relativePath);

            if (!is_dir(dirname($absolutePath))) {
                mkdir(dirname($absolutePath), 0755, true);
            }

            $sql = $this->generarSql($database);

            file_put_contents($absolutePath, $sql);

            $this->info('Respaldo generado correctamente: ' . $absolutePath);

            if ($this->option('mail')) {
                $correoDestino = env('BACKUP_MAIL_TO', env('MAIL_FROM_ADDRESS'));

                if (!$correoDestino) {
                    $this->error('No se encontró BACKUP_MAIL_TO ni MAIL_FROM_ADDRESS.');
                    return self::FAILURE;
                }

                Mail::to($correoDestino)->send(new BackupDatabaseMailable($absolutePath, $fileName));

                $this->info('Respaldo enviado por correo a: ' . $correoDestino);
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error al generar respaldo: ' . $e->getMessage());
            logger()->error('BackupDatabaseCommand ERROR: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function generarSql(string $database): string
    {
        $pdo = DB::connection()->getPdo();

        $sql = "-- Respaldo CEFIRET\n";
        $sql .= "-- Base de datos: {$database}\n";
        $sql .= "-- Fecha: " . now()->format('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $tablesResult = DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');

        foreach ($tablesResult as $row) {
            $rowArray = (array) $row;
            $table = array_values($rowArray)[0];

            $this->line('Exportando tabla: ' . $table);

            $createTableResult = DB::select('SHOW CREATE TABLE `' . $table . '`');
            $createTableArray = (array) $createTableResult[0];
            $createTableSql = $createTableArray['Create Table'];

            $sql .= "\n-- --------------------------------------------------------\n";
            $sql .= "-- Tabla: {$table}\n";
            $sql .= "-- --------------------------------------------------------\n\n";
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sql .= $createTableSql . ";\n\n";

            $rows = DB::table($table)->get();

            foreach ($rows as $record) {
                $recordArray = (array) $record;

                $columns = array_map(function ($column) {
                    return '`' . str_replace('`', '``', $column) . '`';
                }, array_keys($recordArray));

                $values = array_map(function ($value) use ($pdo) {
                    if ($value === null) {
                        return 'NULL';
                    }

                    if (is_int($value) || is_float($value)) {
                        return (string) $value;
                    }

                    return $pdo->quote((string) $value);
                }, array_values($recordArray));

                $sql .= 'INSERT INTO `' . $table . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n";
            }

            $sql .= "\n";
        }

        $sql .= "\nSET FOREIGN_KEY_CHECKS=1;\n";

        return $sql;
    }
}