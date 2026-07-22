<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserClub extends Model
{
    use HasFactory;
    protected $connection = 'mysql_club_bohn';
    protected $table = 'users';
    protected $fillable = [
        'brimagy_id',
        'representative_id',
        'profile',
        'send_to',
        'name',
        'first_last_name',
        'second_last_name',
        'birth',
        'email',
        'password',
        'company',
        'branch_office',
        'distributor',
        'phone',
        'mobile',
        'nextel',
        'api_id',
        'password_status',
        'last_change_of_password',
        'status',
        'remember_token',
        'promotions',
        'encuestas',
        'session',
        'foto',
        'tipo_usuario',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
