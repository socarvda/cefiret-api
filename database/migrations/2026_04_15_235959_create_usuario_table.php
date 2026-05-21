<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('usuario')) {

            Schema::create('usuario', function (Blueprint $table) {
                $table->bigIncrements('id_usuario');
                $table->string('nombre', 100);
                $table->string('apaterno', 100);
                $table->string('amaterno', 100);
                $table->string('correo')->unique();
                $table->string('contrasena');
                $table->string('telefono', 15);
                $table->date('fecha_nac');
                $table->tinyInteger('id_tipo_usuario');
                $table->tinyInteger('activo')->default(1);
                $table->string('token_confirmacion')->nullable();
                $table->string('token_recuperacion')->nullable();
                $table->timestamp('token_expiracion')->nullable();
                $table->timestamps();
            });

        }
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario');
    }
};