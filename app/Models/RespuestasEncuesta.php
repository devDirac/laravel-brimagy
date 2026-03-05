<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RespuestasEncuesta extends Model
{
    use HasFactory;
    protected $table = 'dc_respuestas_encuesta';
    protected $fillable = [
        'id_pregunta',
        'id_canje',
        'respuesta',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
