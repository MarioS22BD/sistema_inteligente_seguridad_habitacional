<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function seedApiRolesAndPermissions(): void
{
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
}

function makeApiUserWithRole(string $rol): User
{
    seedApiRolesAndPermissions();

    $usuario = User::factory()->create([
        'name' => ucfirst($rol),
        'email' => sprintf('%s.%s@seguridad.test', strtolower($rol), uniqid()),
        'rol' => $rol,
    ]);

    $usuario->syncRoles($rol);

    return $usuario;
}
