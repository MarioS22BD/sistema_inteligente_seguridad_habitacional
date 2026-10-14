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
            ->whereIn('rol', ['administrador', 'usuario'])
            ->get()
            ->each(fn (User $usuario) => $usuario->syncRoles($usuario->rol));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
