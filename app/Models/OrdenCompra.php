<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdenCompra extends Model
{
    use HasFactory;
    protected $table = 'dc_orden_compra';
    protected $fillable = [
        'id_usuario',
        'no_orden',
        'id_proveedor',
        'id_usuario',
        'productos_canje',
        'observaciones',
        'estatus',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
