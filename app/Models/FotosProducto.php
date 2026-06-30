<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FotosProducto extends Model
{
    use HasFactory;
    protected $table = 'dc_fotos_producto';
    protected $fillable = [
        'id_producto',
        'id_foto_brimagy',
        'nombre',
        'nombre_original',
        'url_foto',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
