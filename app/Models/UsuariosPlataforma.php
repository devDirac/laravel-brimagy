<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class UsuariosPlataforma extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    //use HasFactory;
    protected $table = 'dc_usuarios_plataforma';
    protected $fillable = [
        'usuario',
        'name',
        'email',
        'telefono',
        'email_verified_at',
        'password',
        'tipo_usuario',
        'foto',
        'status',
        'remember_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];
}
