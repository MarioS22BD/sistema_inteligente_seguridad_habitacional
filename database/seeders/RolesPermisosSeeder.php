<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermisosSeeder extends Seeder
{
    /**
     * Seed application roles and permissions.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'ver_estado_sistema',
            'ver_eventos',
            'crear_eventos',
            'editar_eventos',
            'eliminar_eventos',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate([
                'name' => $permiso,
                'guard_name' => 'web',
            ]);
        }

        $permisosPorRol = [
            'administrador' => $permisos,
            'empleado' => [
                'ver_estado_sistema',
                'ver_eventos',
                'crear_eventos',
            ],
            'usuario' => [
                'ver_estado_sistema',
                'ver_eventos',
            ],
        ];

        foreach ($permisosPorRol as $nombreRol => $permisosRol) {
            $rol = Role::firstOrCreate([
                'name' => $nombreRol,
                'guard_name' => 'web',
            ]);

            $rol->syncPermissions($permisosRol);
        }

        User::query()
            ->whereIn('email', [
                'admin@seguridad.local',
                'empleado@seguridad.local',
                'usuario@seguridad.local',
            ])
            ->get()
            ->each(function (User $usuario): void {
                if (in_array($usuario->rol, ['administrador', 'empleado', 'usuario'], true)) {
                    $usuario->syncRoles($usuario->rol);
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
