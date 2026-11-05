<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FotosOfertasClub extends Model
{
    use HasFactory;
    protected $connection = 'mysql_club_bohn';
    protected $table = 'rel_offers_in_award';
    protected $fillable = [
        'award_id',
        'offer',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
