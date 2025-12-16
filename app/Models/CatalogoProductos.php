<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogoProductos extends Model
{
    use HasFactory;
    protected $table = 'dc_catalogo_productos';
    protected $fillable = [
        'nombre_producto',
        'descripcion',
        'marca',
        'sku',
        'color',
        'id_proveedor',
        'id_catalogo',
        'costo_con_iva',
        'costo_sin_iva',
        'costo_puntos_con_iva',
        'costo_puntos_sin_iva',
        'fee_brimagy',
        'subtotal',
        'envio_base',
        'costo_caja',
        'envio_extra',
        'total_envio',
        'total',
        'puntos',
        'factor'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
