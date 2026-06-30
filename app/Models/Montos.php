<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Montos extends Model
{
    use HasFactory;
    protected $table = 'dc_montos_digital';
    protected $fillable = [
        'id_producto',
        'id_monto_brimagy',
        'monto',
        'puntos',
        'descripcion',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
