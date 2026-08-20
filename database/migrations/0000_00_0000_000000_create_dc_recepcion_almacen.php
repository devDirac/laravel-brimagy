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
        Schema::create('dc_recepcion_almacen', function (Blueprint $table) {
            $table->id();
            $table->integer('id_canje');
            $table->integer('id_usuario');
            $table->integer('id_producto');
            $table->integer('id_producto_nuevo')->nullable();
            $table->integer('id_orden_compra');
            $table->integer('cantidad_producto');
            $table->integer('cantidad_almacen')->nullable();
            $table->integer('id_proveedor')->nullable();
            $table->integer('precio_compra')->nullable();
            $table->date('fecha_compra')->nullable();
            $table->integer('costo_envio_real')->nullable();
            $table->string('folio_factura')->nullable();
            $table->string('comentarios')->nullable();
            $table->enum('estatus', ['con_detalles', 'por_recibir', 'en_almacen', 'en_almacen_parcialmente', 'guia_asignada', 'enviado', 'entregado'])->default('por_recibir');
            $table->string('imei')->nullable();
            $table->string('no_serie')->nullable();
            $table->string('guia')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dc_recepcion_almacen');
    }
};
