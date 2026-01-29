<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Facturas extends Model
{
    use HasFactory;
    protected $table = 'dc_facturas';
    protected $fillable = [
        'id_orden_compra',
        'id_proveedor',
        'id_usuario',
        'nombre_factura',
        'tipo_archivo',
        'url_factura'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
