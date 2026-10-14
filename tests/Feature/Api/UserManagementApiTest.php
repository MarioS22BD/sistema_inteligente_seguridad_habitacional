<?php

use App\Models\User;

beforeEach(fn () => seedApiRolesAndPermissions());

it('requires authentication and administrator authorization to manage users', function (): void {
    $this->getJson('/api/v1/usuarios')->assertUnauthorized();

    $user = makeApiUserWithRole('usuario');
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/usuarios')
        ->assertForbidden();
});

it('allows administrators to list and search users with pagination', function (): void {
    $admin = makeApiUserWithRole('administrador');
    User::factory()->create(['name' => 'Contacto Operativo', 'rol' => 'usuario']);
    User::factory()->create(['name' => 'Otro Administrador', 'rol' => 'administrador']);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/usuarios?search=Contacto&per_page=1')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Contacto Operativo');
});

it('allows administrators to create only the supported roles', function (): void {
    $admin = makeApiUserWithRole('administrador');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/usuarios', [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo.usuario@seguridad.test',
            'password' => 'password123',
            'rol' => 'usuario',
        ])
        ->assertCreated()
        ->assertJsonPath('data.rol', 'usuario')
        ->assertJsonMissingPath('data.password');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/usuarios', [
            'name' => 'Rol no válido',
            'email' => 'rol.invalido@seguridad.test',
            'password' => 'password123',
            'rol' => 'empleado',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('rol');

    $createdUser = User::query()->where('email', 'nuevo.usuario@seguridad.test')->firstOrFail();
    expect($createdUser->email_verified_at)->not->toBeNull();
});

it('allows only administrators to update and delete users', function (): void {
    $admin = makeApiUserWithRole('administrador');
    $user = makeApiUserWithRole('usuario');
    $target = User::factory()->create(['rol' => 'usuario']);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/v1/usuarios/'.$target->id, ['name' => 'No permitido'])
        ->assertForbidden();
    $this->actingAs($user, 'sanctum')
        ->deleteJson('/api/v1/usuarios/'.$target->id)
        ->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->patchJson('/api/v1/usuarios/'.$target->id, [
            'name' => 'Nombre actualizado',
            'rol' => 'administrador',
        ])
        ->assertOk()
        ->assertJsonPath('data.rol', 'administrador');

    $this->actingAs($admin, 'sanctum')
        ->deleteJson('/api/v1/usuarios/'.$target->id)
        ->assertNoContent();
});
