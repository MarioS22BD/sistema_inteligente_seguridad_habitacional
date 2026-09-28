<?php

use App\Models\EstadoSistema;
use App\Models\User;

beforeEach(fn () => seedApiRolesAndPermissions());

it('requiere autenticación para consultar el estado del sistema', function (): void {
    $this->getJson('/api/v1/estado-sistema')
        ->assertStatus(401);
});

it('permite consultar el estado del sistema a usuarios con el permiso necesario', function (): void {
    $usuario = makeApiUserWithRole('usuario');
    $estado = EstadoSistema::query()->create([
        'esta_activado' => true,
        'puerta_abierta' => true,
        'movimiento_detectado' => false,
        'estado' => 'ALARMA',
        'descripcion_ultima_alerta' => 'Se detectó la apertura de la puerta mientras el sistema estaba activado.',
        'fecha_ultima_alerta' => now(),
    ]);

    $this->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/estado-sistema')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'esta_activado',
                'puerta_abierta',
                'movimiento_detectado',
                'estado',
                'descripcion_ultima_alerta',
                'fecha_ultima_alerta',
                'created_at',
                'updated_at',
            ],
        ])
        ->assertJsonPath('data.id', $estado->id)
        ->assertJsonPath('data.estado', 'ALARMA');
});

it('deniega la consulta del estado del sistema cuando el usuario no tiene permiso', function (): void {
    $usuario = User::factory()->create([
        'rol' => 'usuario',
    ]);

    EstadoSistema::query()->create([
        'esta_activado' => false,
        'puerta_abierta' => false,
        'movimiento_detectado' => false,
        'estado' => 'NORMAL',
    ]);

    $this->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/estado-sistema')
        ->assertForbidden();
});
