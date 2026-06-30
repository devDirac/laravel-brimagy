<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ColoresBrimagy extends Model
{
    use HasFactory;
    protected $connection = 'mysql_brimagy';
    protected $table = 'colors_for_the_award_dirac';
    protected $fillable = [
        'award_id',
        'color',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
