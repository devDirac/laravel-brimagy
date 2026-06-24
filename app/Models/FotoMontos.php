<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FotoMontos extends Model
{
    use HasFactory;
    protected $table = 'dc_foto_montos';
    protected $fillable = [
        'id_producto',
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
