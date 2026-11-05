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
        Schema::create('dc_orden_compra', function (Blueprint $table) {
            $table->id();
            $table->string('no_orden');
            $table->foreignId('id_proveedor')->nullable()->constrained('dc_catalogo_proveedores')->onDelete('cascade')->change();
            $table->foreignId('id_usuario')->constrained('users')->onDelete('cascade');
            $table->json('productos_canje');
            $table->longText('observaciones')->nullable();
            $table->enum('estatus', ['cotizacion_enviada_a_proveedor', 'cotizacion_validada_por_proveedor', 'orden_compra_enviada_a_proveedor', 'xml_validado_correctamente_proveedor', 'factura_subida_correctamente_proveedor', 'orden_validada_por_proveedor', 'cotizacion_rechazada'])->default('cotizacion_enviada_a_proveedor');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dc_orden_compra');
    }
};
