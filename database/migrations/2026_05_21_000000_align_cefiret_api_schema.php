<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('usuario')) {
            Schema::table('usuario', function (Blueprint $table) {
                if (!Schema::hasColumn('usuario', 'activo')) {
                    $table->tinyInteger('activo')->default(1)->after('id_tipo_usuario');
                }
                if (!Schema::hasColumn('usuario', 'token_confirmacion')) {
                    $table->string('token_confirmacion')->nullable()->after('activo');
                }
                if (!Schema::hasColumn('usuario', 'token_recuperacion')) {
                    $table->string('token_recuperacion')->nullable()->after('token_confirmacion');
                }
                if (!Schema::hasColumn('usuario', 'token_expiracion')) {
                    $table->timestamp('token_expiracion')->nullable()->after('token_recuperacion');
                }
                if (!Schema::hasColumn('usuario', 'api_token')) {
                    $table->string('api_token', 80)->nullable()->unique()->after('token_expiracion');
                }
            });
        }

        if (Schema::hasTable('cita') && !Schema::hasColumn('cita', 'google_event_id')) {
            Schema::table('cita', function (Blueprint $table) {
                $table->string('google_event_id')->nullable()->after('estatus');
            });
        }

        if (Schema::hasTable('expediente')) {
            DB::statement('ALTER TABLE expediente MODIFY alimentacion VARCHAR(100) NULL');
        }

        if (Schema::hasTable('habitos_higien')) {
            DB::statement('ALTER TABLE habitos_higien MODIFY lavado_manos VARCHAR(100) NULL');
            DB::statement('ALTER TABLE habitos_higien MODIFY lavado_dientes VARCHAR(100) NULL');
            DB::statement('ALTER TABLE habitos_higien MODIFY cambio_ropa VARCHAR(100) NULL');
            DB::statement('ALTER TABLE habitos_higien MODIFY revision_pies VARCHAR(100) NULL');
            DB::statement('ALTER TABLE habitos_higien MODIFY horas_sueno VARCHAR(50) NULL');
        }

        if (Schema::hasTable('vivienda')) {
            DB::statement('ALTER TABLE vivienda MODIFY agua VARCHAR(100) NULL');
            DB::statement('ALTER TABLE vivienda MODIFY luz VARCHAR(100) NULL');
            DB::statement('ALTER TABLE vivienda MODIFY drenaje VARCHAR(100) NULL');
            DB::statement('ALTER TABLE vivienda MODIFY gas VARCHAR(100) NULL');
            DB::statement('ALTER TABLE vivienda MODIFY limpieza_hogar VARCHAR(100) NULL');
        }

        if (Schema::hasTable('rutina_dias')) {
            DB::statement('ALTER TABLE rutina_dias CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            DB::statement('ALTER TABLE rutina_dias MODIFY dia VARCHAR(20) NOT NULL');
        }

        if (Schema::hasTable('disponibilidad')) {
            DB::statement('ALTER TABLE disponibilidad CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            DB::statement('ALTER TABLE disponibilidad MODIFY dia VARCHAR(20) NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('usuario')) {
            Schema::table('usuario', function (Blueprint $table) {
                foreach (['api_token', 'token_expiracion', 'token_recuperacion', 'token_confirmacion', 'activo'] as $column) {
                    if (Schema::hasColumn('usuario', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('cita') && Schema::hasColumn('cita', 'google_event_id')) {
            Schema::table('cita', function (Blueprint $table) {
                $table->dropColumn('google_event_id');
            });
        }
    }
};
