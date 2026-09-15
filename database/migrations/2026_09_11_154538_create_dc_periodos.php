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
        Schema::create('dc_periodos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_plataforma');
            $table->unsignedInteger('id_usuario_creador');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->unsignedInteger('activo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dc_periodos');
    }
};
