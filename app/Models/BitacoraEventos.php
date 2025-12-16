<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BitacoraEventos extends Model
{
    use HasFactory;
    protected $table = 'bitacora_eventos';
    public $timestamps = false;
    protected $fillable = [
        'evento',
        'descripcion',
        'tabla',
        'id_referencia',
        'id_usuario'
    ];
}
