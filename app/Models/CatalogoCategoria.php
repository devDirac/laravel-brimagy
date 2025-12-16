<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogoCategoria extends Model
{
    use HasFactory;
    protected $table = 'dc_catalogo_categoria';
    protected $fillable = [
        'nombre',
        'descripcion'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
