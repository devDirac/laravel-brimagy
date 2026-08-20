<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductoEditado extends Model
{
    use HasFactory;
    protected $table = 'dc_producto_editado';
    protected $fillable = [
        'id_producto',
        'editado'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
