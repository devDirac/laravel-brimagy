<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductoClub extends Model
{
    use HasFactory;
    protected $connection = 'mysql_club_bohn';
    protected $table = 'awards';
    protected $fillable = [
        'desc',
        'required_score',
        'sub_category_id',
        'photo_name',
        'sku',
        'features',
        'TyC',
        'validity',
        'status',
        'stock',
        'score_promotions',
        'new',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
