<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogoTipoUsuarios extends Model
{
    use HasFactory;
    protected $table = 'dc_catalogo_tipo_usuarios';
    protected $fillable = [
        'id_plataforma',
        'nombre',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
