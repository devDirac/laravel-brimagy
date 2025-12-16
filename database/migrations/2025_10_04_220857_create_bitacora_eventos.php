<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bitacora_eventos', function (Blueprint $table) {
            $table->id();
            $table->string(column: 'evento');
            $table->string(column: 'descripcion');
            $table->string(column: 'tabla')->nullable();
            $table->integer('id_referencia')->nullable();
            $table->integer('id_usuario');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bitacora_eventos');
    }
};
