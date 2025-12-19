<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogoProveedores extends Model
{
    use HasFactory;
    protected $table = 'dc_catalogo_proveedores';
    protected $fillable = [
        'nombre',
        'descripcion',
        'telefono',
        'correo',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
