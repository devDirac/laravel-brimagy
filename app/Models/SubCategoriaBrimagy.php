<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubCategoriaBrimagy extends Model
{
    use HasFactory;
    protected $connection = 'mysql_brimagy';
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

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaBrimagy::class, 'category_id');
    }
}
