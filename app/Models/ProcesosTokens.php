<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcesosTokens extends Model
{
    use HasFactory;
    protected $table = 'procesos_tokens';
    public $timestamps = false;
    protected $fillable = [
        'proceso'
    ];
}