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
        Schema::create('dc_evidencias_almacen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_almacen_producto')->constrained('dc_recepcion_almacen')->onDelete('cascade');
            $table->json('evidencias');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dc_evidencias_almacen');
    }
};
