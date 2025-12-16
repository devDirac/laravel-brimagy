<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class TipoUsuarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('tipo_usuarios')->insert([
            'permiso' => 'Usuario',
        ]);
        DB::table('tipo_usuarios')->insert([
            'permiso' => 'Editor',
        ]);
        DB::table('tipo_usuarios')->insert([
            'permiso' => 'Super Admin',
        ]);
    }
}
