<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notificaciones extends Model
{
    use HasFactory;
    protected $table = 'dc_notificaciones';

    protected $fillable = [
        'detalle',
        'creada',
        'vista_fecha',
        'vista',
        'id_tipo_notificacion',
        'id_usuario_creador',
        'id_usuario_para',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
