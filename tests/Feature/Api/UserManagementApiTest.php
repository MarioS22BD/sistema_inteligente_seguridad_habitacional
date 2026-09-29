<?php

use App\Models\User;

beforeEach(fn () => seedApiRolesAndPermissions());

it('requires authentication to manage users', function (): void {
    $this->getJson('/api/v1/usuarios')->assertUnauthorized();
});

it('allows administrators to list and search all roles with pagination', function (): void {
    $admin = makeApiUserWithRole('administrador');
    User::factory()->create(['name' => 'Contacto Operativo', 'rol' => 'usuario']);
    User::factory()->create(['name' => 'Empleado Demo', 'rol' => 'empleado']);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/usuarios?search=Contacto&per_page=1')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Contacto Operativo');
});

it('limits employee listing to normal users', function (): void {
    $employee = makeApiUserWithRole('empleado');
    User::factory()->create(['rol' => 'usuario']);
    User::factory()->create(['rol' => 'administrador']);
    User::factory()->create(['rol' => 'empleado']);

    $this->actingAs($employee, 'sanctum')
        ->getJson('/api/v1/usuarios')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.rol', 'usuario');
});

it('allows administrators to create users with any role', function (): void {
    $admin = makeApiUserWithRole('administrador');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/usuarios', [
            'name' => 'Nuevo Administrador',
            'email' => 'nuevo.admin@seguridad.test',
            'password' => 'password123',
            'rol' => 'administrador',
        ])
        ->assertCreated()
        ->assertJsonPath('data.rol', 'administrador')
        ->assertJsonMissingPath('data.password');

    $this->assertDatabaseHas('users', [
        'email' => 'nuevo.admin@seguridad.test',
        'rol' => 'administrador',
    ]);
});

it('allows employees to create normal users but not privileged roles', function (): void {
    $employee = makeApiUserWithRole('empleado');

    $this->actingAs($employee, 'sanctum')
        ->postJson('/api/v1/usuarios', [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo.usuario@seguridad.test',
            'password' => 'password123',
            'rol' => 'usuario',
        ])
        ->assertCreated();

    $this->actingAs($employee, 'sanctum')
        ->postJson('/api/v1/usuarios', [
            'name' => 'Nuevo Empleado',
            'email' => 'nuevo.empleado@seguridad.test',
            'password' => 'password123',
            'rol' => 'empleado',
        ])
        ->assertForbidden();
});

it('allows employees to update normal-user contact data but not roles or privileged accounts', function (): void {
    $employee = makeApiUserWithRole('empleado');
    $normalUser = User::factory()->create(['rol' => 'usuario']);
    $otherEmployee = User::factory()->create(['rol' => 'empleado']);

    $this->actingAs($employee, 'sanctum')
        ->patchJson('/api/v1/usuarios/'.$normalUser->id, [
            'name' => 'Nombre actualizado',
            'email' => 'contacto.actualizado@seguridad.test',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nombre actualizado');

    $this->actingAs($employee, 'sanctum')
        ->patchJson('/api/v1/usuarios/'.$normalUser->id, ['rol' => 'administrador'])
        ->assertForbidden();

    $this->actingAs($employee, 'sanctum')
        ->patchJson('/api/v1/usuarios/'.$otherEmployee->id, ['name' => 'No permitido'])
        ->assertForbidden();
});

it('reserves user deletion for administrators', function (): void {
    $admin = makeApiUserWithRole('administrador');
    $employee = makeApiUserWithRole('empleado');
    $target = User::factory()->create(['rol' => 'usuario']);

    $this->actingAs($employee, 'sanctum')
        ->deleteJson('/api/v1/usuarios/'.$target->id)
        ->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->deleteJson('/api/v1/usuarios/'.$target->id)
        ->assertNoContent();

    $this->assertDatabaseMissing('users', ['id' => $target->id]);
});
