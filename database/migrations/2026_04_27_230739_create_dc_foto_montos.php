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
        Schema::create('dc_foto_montos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_producto')->constrained('dc_catalogo_productos')->onDelete('cascade');
            $table->string('nombre');
            $table->string('nombre_original');
            $table->string('url_foto');
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dc_foto_montos');
    }
};
