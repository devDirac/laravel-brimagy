<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ValidacionCanje extends Model
{
    use HasFactory;
    protected $table = 'dc_validacion_canje';
    protected $fillable = [
        'id_canje',
        'id_usuario_admin',
        'id_canje',
        'id_producto',
        'cantidad_producto',
        'id_proveedor',
        'no_orden',
        'fecha_validacion',
        'codigo_validacion',
        'id_plataforma',
        'estatus'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
