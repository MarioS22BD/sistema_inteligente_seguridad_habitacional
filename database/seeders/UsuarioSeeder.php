<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

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
                'name' => 'Usuario',
                'email' => 'usuario@seguridad.local',
                'rol' => 'usuario',
            ],
        ];

        foreach ($usuarios as $usuario) {
            $cuenta = User::firstOrCreate(
                ['email' => $usuario['email']],
                [
                    'name' => $usuario['name'],
                    'rol' => $usuario['rol'],
                    'password' => Hash::make('password123'),
                ],
            );

            if ($cuenta->wasRecentlyCreated && $cuenta->email_verified_at === null) {
                $cuenta->forceFill(['email_verified_at' => now()])->save();
            }

            if (Role::where('name', $usuario['rol'])->where('guard_name', 'web')->exists()) {
                if (! $cuenta->hasRole($usuario['rol'], 'web')) {
                    $cuenta->assignRole($usuario['rol']);
                }
            }
        }
    }
}
