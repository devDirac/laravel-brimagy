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
        Schema::create('dc_catalogo_productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_producto');
            $table->text('descripcion')->nullable();
            $table->string('marca')->nullable();
            $table->string('sku')->nullable();
            $table->string('color')->nullable();
            $table->string('talla')->nullable();
            $table->foreignId('id_proveedor')->nullable()->constrained('dc_catalogo_proveedores')->onDelete('cascade');
            $table->foreignId('id_catalogo')->constrained('dc_categoria_catalogo')->onDelete('cascade');
            $table->decimal('costo_con_iva', 8, 4);
            $table->decimal('costo_sin_iva', 8, 4);
            $table->decimal('costo_puntos_con_iva', 8, 4);
            $table->decimal('costo_puntos_sin_iva', 8, 4);
            $table->decimal('fee_brimagy', 8, 4);
            $table->decimal('subtotal', 8, 4);
            $table->decimal('envio_base', 8, 4);
            $table->decimal('costo_caja', 8, 4);
            $table->decimal('envio_extra', 8, 4);
            $table->decimal('total_envio', 8, 4)->nullable();
            $table->decimal('total', 8, 4);
            $table->decimal('puntos', 8, 4);
            $table->decimal('valor_factor', 2, 2)->nullable();
            $table->integer('factor');
            $table->string('foto_producto')->nullable();
            $table->integer('id_producto_brimagy')->nullable();
            $table->foreignId('id_plataforma')->constrained('dc_plataformas')->onDelete('cascade');
            $table->enum('tipo_producto', ['fisico', 'digital'])->default('fisico');
            $table->integer('stock')->nullable();
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
