<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubCategoria extends Model
{
    use HasFactory;
    protected $table = 'sub_categories';
    protected $fillable = [
        'category_id',
        'desc',
        'status',
        'file_path',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
