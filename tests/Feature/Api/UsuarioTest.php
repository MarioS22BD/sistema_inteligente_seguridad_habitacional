<?php

beforeEach(fn () => seedApiRolesAndPermissions());

it('requiere autenticación para consultar el usuario autenticado', function (): void {
    $this->getJson('/api/v1/usuario')
        ->assertStatus(401);
});

it('devuelve los datos del usuario autenticado cuando está autorizado', function (): void {
    $usuario = makeApiUserWithRole('administrador');

    $this->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/usuario')
        ->assertOk()
        ->assertJsonPath('id', $usuario->id)
        ->assertJsonPath('email', $usuario->email)
        ->assertJsonPath('rol', $usuario->rol)
        ->assertJsonPath('roles.0', 'administrador');
});
