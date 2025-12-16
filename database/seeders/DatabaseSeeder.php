<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        DB::table('users')->insert([
            'usuario' => 'admin-esqueleto',
            'nombre' => 'Jorge Carrera',
            'correo' => 'carrera.jorge@dirac.mx',
            'telefono' => '5555555555',
            'password' => bcrypt('admin_esqueleto123'),
            'tipo_usuario' => 3,
            'activo' => 1,
        ]);
    }
}
