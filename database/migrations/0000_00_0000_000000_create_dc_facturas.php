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
        Schema::create('dc_facturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_orden_compra')->constrained('dc_orden_compra')->onDelete('cascade');
            $table->integer('id_proveedor')->nullable();
            $table->foreignId('id_usuario')->constrained('users')->onDelete('cascade');
            $table->string('nombre_factura');
            $table->string('tipo_archivo');
            $table->string('url_factura');
            $table->date('fecha_pago')->nullable();
            $table->enum('estatus', ['sin_pagar', 'pagada'])->default('sin_pagar');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dc_facturas');
    }
};
