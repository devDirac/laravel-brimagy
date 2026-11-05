<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Encuestas extends Model
{
    use HasFactory;
    protected $table = 'dc_encuestas';
    protected $fillable = [
        'pregunta',
        'tipo_encuesta',
        'tipo_pregunta',
        'id_plataforma',
        'estatus',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
