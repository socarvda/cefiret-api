<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cita')) {

            Schema::create('cita', function (Blueprint $table) {
                $table->bigIncrements('id_cita');
                $table->unsignedBigInteger('id_usuario');
                $table->unsignedBigInteger('id_fisioterapeuta');
                $table->date('fecha');
                $table->time('hora');
                $table->string('motivo')->nullable();
                $table->string('estatus')->default('programada');
                $table->string('google_event_id')->nullable();
                $table->timestamps();

                $table->index('id_usuario');
                $table->index('id_fisioterapeuta');
                $table->index('fecha');
                $table->index('hora');
            });

        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cita');
    }
};