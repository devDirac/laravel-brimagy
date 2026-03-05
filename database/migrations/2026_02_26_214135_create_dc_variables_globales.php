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
        Schema::create('dc_variables_globales', function (Blueprint $table) {
            $table->id();
            $table->integer('fee_brimagy');
            $table->integer('envio_base');
            $table->integer('costo_caja');
            $table->integer('envio_extra');
            $table->foreignId('id_plataforma')->constrained('dc_plataformas')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dc_variables_globales');
    }
};
