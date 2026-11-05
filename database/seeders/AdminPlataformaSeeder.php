<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminPlataformaSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('dc_usuarios_plataforma')->updateOrInsert(
            ['email' => 'carrera.jorge@dirac.mx'],
            [
                'usuario' => 'admin-esqueleto',
                'name' => 'Jorge Carrera',
                'telefono' => '5555555555',
                'password' => bcrypt(env('ADMIN_SEED_PASSWORD', 'cambiar-esta-clave')),
                'tipo_usuario' => 7,
                'status' => 'ACTIVE',
                'updated_at' => now(),
            ]
        );
    }
}