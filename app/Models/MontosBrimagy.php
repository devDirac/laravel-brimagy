<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MontosBrimagy extends Model
{
    use HasFactory;
    protected $connection = 'mysql_brimagy';
    protected $table = 'gifs';
    protected $fillable = [
        'award_id',
        'monto',
        'points',
        'descripcion',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
