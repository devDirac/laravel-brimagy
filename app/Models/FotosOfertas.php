<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FotosOfertas extends Model
{
    use HasFactory;
    protected $table = 'dc_fotos_promo_producto';
    protected $fillable = [
        'id_producto',
        'id_foto_promo_brimagy',
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
