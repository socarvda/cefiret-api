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
                if (!Schema::hasColumn('usuario', 'api_token')) {
                    $table->string('api_token', 80)->nullable()->unique()->after('token_expiracion');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('usuario') && Schema::hasColumn('usuario', 'api_token')) {
            Schema::table('usuario', function (Blueprint $table) {
                $table->dropColumn('api_token');
            });
        }
    }
};