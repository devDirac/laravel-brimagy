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
        Schema::create('dc_validacion_canje', function (Blueprint $table) {
            $table->id();
            $table->integer('id_usuario_admin');
            $table->integer('id_canje');
            $table->datetime('fecha_validacion')->nullable();
            $table->integer('codigo_validacion')->nullable();
            $table->enum('estatus', ['notificacion_enviada', 'solicitud_enviada', 'identidad_validada'])->default('notificacion_enviada');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dc_validacion_canje');
    }
};
