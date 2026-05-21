<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
        if (Schema::hasTable('usuario')) {
            Schema::table('usuario', function (Blueprint $table) {
                if (!Schema::hasColumn('usuario', 'activo')) {
                    $table->boolean('activo')->default(1)->after('telefono');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('usuario')) {
            Schema::table('usuario', function (Blueprint $table) {
                if (Schema::hasColumn('usuario', 'activo')) {
                    $table->dropColumn('activo');
                }
            });
        }
    }
};
