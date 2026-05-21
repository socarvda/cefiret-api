<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('usuario')) {
            Schema::table('usuario', function (Blueprint $table) {
                if (Schema::hasColumn('usuario', 'token_expiracion')) {
                    $table->dropColumn('token_expiracion');
                }
                if (Schema::hasColumn('usuario', 'token_recuperacion')) {
                    $table->dropColumn('token_recuperacion');
                }
                if (Schema::hasColumn('usuario', 'token_confirmacion')) {
                    $table->dropColumn('token_confirmacion');
                }
                if (Schema::hasColumn('usuario', 'activo')) {
                    $table->dropColumn('activo');
                }
            });
        }
    }
};
