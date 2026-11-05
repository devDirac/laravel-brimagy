<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FotosProductoLocal extends Model
{
    use HasFactory;
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
