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
        'fecha_validacion',
        'codigo_validacion',
        'estatus'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
