<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    /**
     * Seed the application's users.
     */
    public function run(): void
    {
        $usuarios = [
            [
                'name' => 'Administrador',
                'email' => 'admin@seguridad.local',
                'rol' => 'administrador',
            ],
            [
                'name' => 'Empleado',
                'email' => 'empleado@seguridad.local',
                'rol' => 'empleado',
            ],
            [
                'name' => 'Usuario',
                'email' => 'usuario@seguridad.local',
                'rol' => 'usuario',
            ],
        ];

        foreach ($usuarios as $usuario) {
            User::updateOrCreate(
                ['email' => $usuario['email']],
                [
                    'name' => $usuario['name'],
                    'rol' => $usuario['rol'],
                    'password' => Hash::make('password123'),
                ],
            );
        }
    }
}
