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
            $table->decimal('fee_brimagy', 6, 4);
            $table->decimal('envio_base', 6, 4);
            $table->decimal('costo_caja', 6, 4);
            $table->decimal('envio_extra', 6, 4);
            $table->decimal('factor', 6, 4);
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
