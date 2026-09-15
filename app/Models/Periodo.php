<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Periodo extends Model
{
    use HasFactory;
    protected $table = 'dc_periodos';
    protected $fillable = [
        'id_plataforma',
        'id_usuario_creador',
        'fecha_inicio',
        'fecha_fin',
        'activo'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
