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
        Schema::create('dc_encuestas', function (Blueprint $table) {
            $table->id();
            $table->string('pregunta');
            $table->enum('tipo_encuesta', ['satisfaccion_compra', 'satisfaccion_plataforma'])->default('satisfaccion_compra');
            $table->enum('tipo_pregunta', ['abierta', 'opcion_multiple', 'si_no'])->default('opcion_multiple');
            $table->enum('estatus', ['desactivada', 'activa'])->default('activa');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dc_encuestas');
    }
};
