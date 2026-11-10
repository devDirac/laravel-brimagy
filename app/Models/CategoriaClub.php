<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaClub extends Model
{
    use HasFactory;
    protected $connection = 'mysql_club_bohn';
    protected $table = 'awards_categories';
    protected $fillable = [
        'desc',
        'status',
        'file_path',
        'custom_order',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function subcategorias(): HasMany
    {
        return $this->hasMany(SubCategoriaClub::class, 'category_id');
    }
}
