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
        Schema::create('dc_catalogo_productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_producto');
            $table->string('descripcion');
            $table->string('marca');
            $table->string('sku');
            $table->string('color')->nullable();
            $table->foreignId('id_proveedor')->constrained('dc_catalogo_proveedores')->onDelete('cascade');
            $table->foreignId('id_catalogo')->constrained('dc_categoria_catalogo')->onDelete('cascade');
            $table->integer('costo_con_iva');
            $table->integer('costo_sin_iva');
            $table->integer('costo_puntos_con_iva');
            $table->integer('costo_puntos_sin_iva');
            $table->integer('fee_brimagy');
            $table->integer('subtotal');
            $table->integer('envio_base');
            $table->integer('costo_caja');
            $table->integer('envio_extra');
            $table->integer('total_envio')->nullable();
            $table->integer('total');
            $table->integer('puntos');
            $table->integer('factor');
            $table->enum('tipo_producto', ['fisico', 'digital'])->default('fisico');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dc_catalogo_productos');
    }
};
