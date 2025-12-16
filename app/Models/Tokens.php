<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tokens extends Model
{
    use HasFactory;
    protected $table = 'tokens';
    public $timestamps = false;
    protected $fillable = [
        'token',
        'id_usuario',
        'id_token_proceso',
        'ids',
        'tabla'
    ];
}