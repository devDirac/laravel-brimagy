<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecepcionAlmacen extends Model
{
    use HasFactory;
    protected $table = 'dc_recepcion_almacen';
    protected $fillable = [
        'id_canje',
        'id_usuario',
        'id_producto',
        'id_orden_compra',
        'cantidad_producto',
        'cantidad_almacen',
        'fecha',
        'comentarios',
        'estatus',
        'guia',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
