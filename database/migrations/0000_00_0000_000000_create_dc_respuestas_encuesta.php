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
        Schema::create('dc_respuestas_encuesta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_pregunta')->constrained('dc_encuestas')->onDelete('restrict');
            $table->integer('id_canje');
            $table->string('respuesta');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dc_respuestas_encuesta');
    }
};
