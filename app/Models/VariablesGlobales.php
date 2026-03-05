<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VariablesGlobales extends Model
{
    use HasFactory;
    protected $table = 'dc_variables_globales';
    protected $fillable = [
        'fee_brimagy',
        'envio_base',
        'costo_caja',
        'envio_extra',
        'id_plataforma',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
