<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FotosProductoClub extends Model
{
    use HasFactory;
    protected $connection = 'mysql_club_bohn';
    protected $table = 'photos_for_the_award';
    protected $fillable = [
        'award_id',
        'photo',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
