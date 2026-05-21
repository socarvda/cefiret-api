<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cita', function (Blueprint $table) {
            if (!Schema::hasColumn('cita', 'google_event_id')) {
                $table->string('google_event_id')->nullable()->after('estatus')->comment('ID del evento en Google Calendar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cita', function (Blueprint $table) {
            if (Schema::hasColumn('cita', 'google_event_id')) {
                $table->dropColumn('google_event_id');
            }
        });
    }
};
