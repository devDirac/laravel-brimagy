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
        'talla',
        'id_proveedor',
        'id_catalogo',
        'id_producto_dirac',
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
        'valor_factor',
        'factor',
        'foto_producto',
        'id_producto_brimagy',
        'id_plataforma',
        'tipo_producto',
        'stock'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
