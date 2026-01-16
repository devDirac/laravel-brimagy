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
        Schema::create('dc_productos_almacen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_proveedor')->constrained('dc_catalogo_proveedores')->onDelete('cascade');
            $table->foreignId('id_usuario')->constrained('users')->onDelete('cascade');
            $table->json('productos_almacen');
            $table->text('observaciones')->nullable();
            $table->enum('estatus', ['por_recibir', 'productos_en_almacen', 'salida_parcial_de_productos', 'salida_total_productos'])->default('por_recibir');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dc_productos_almacen');
    }
};
