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
        Schema::create('dc_notificaciones', function (Blueprint $table) {
            $table->id();
            $table->string('detalle');
            $table->timestamp('creada')->nullable()->default(now());
            $table->timestamp('vista_fecha')->nullable();
            $table->integer('vista');
            $table->integer('id_tipo_notificacion');
            $table->unsignedBigInteger('id_usuario_creador')->nullable();
            $table->unsignedBigInteger('id_usuario_para')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dc_notificaciones');
    }
};
