<?php

use App\Models\EventoSeguridad;
use App\Models\User;

beforeEach(fn () => seedApiRolesAndPermissions());

it('requiere autenticación para consultar el listado de eventos', function (): void {
    $this->getJson('/api/v1/eventos')
        ->assertStatus(401);
});

it('devuelve el listado de eventos para usuarios con permiso de lectura', function (): void {
    $usuario = makeApiUserWithRole('empleado');
    EventoSeguridad::query()->insert([
        [
            'tipo_evento' => 'PUERTA_ABIERTA',
            'descripcion' => 'La puerta fue abierta.',
            'gravedad' => 'advertencia',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'tipo_evento' => 'MOVIMIENTO_DETECTADO',
            'descripcion' => 'Se detectó movimiento.',
            'gravedad' => 'advertencia',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/eventos')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'tipo_evento',
                    'descripcion',
                    'gravedad',
                    'fecha_creacion',
                ],
            ],
        ])
        ->assertJsonCount(2, 'data');
});

it('crea un evento cuando el usuario tiene permiso para crear eventos', function (): void {
    $usuario = makeApiUserWithRole('empleado');

    $this->actingAs($usuario, 'sanctum')
        ->postJson('/api/v1/eventos', [
            'tipo_evento' => 'ALARMA_DISPARADA',
            'descripcion' => 'Se disparó la alarma por movimiento detectado.',
            'gravedad' => 'critico',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.tipo_evento', 'ALARMA_DISPARADA')
        ->assertJsonPath('data.gravedad', 'critico');

    $this->assertDatabaseHas('eventos_seguridad', [
        'tipo_evento' => 'ALARMA_DISPARADA',
        'descripcion' => 'Se disparó la alarma por movimiento detectado.',
        'gravedad' => 'critico',
    ]);
});

it('rechaza la creación de eventos con datos inválidos', function (): void {
    $usuario = makeApiUserWithRole('empleado');

    $this->actingAs($usuario, 'sanctum')
        ->postJson('/api/v1/eventos', [
            'tipo_evento' => 'ALARMA_DISPARADA',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['descripcion', 'gravedad']);
});

it('actualiza un evento cuando el usuario tiene permiso para editar', function (): void {
    $usuario = makeApiUserWithRole('administrador');
    $evento = EventoSeguridad::query()->create([
        'tipo_evento' => 'PUERTA_ABIERTA',
        'descripcion' => 'Original',
        'gravedad' => 'advertencia',
    ]);

    $this->actingAs($usuario, 'sanctum')
        ->putJson('/api/v1/eventos/'.$evento->id, [
            'descripcion' => 'Modificado por administración',
            'gravedad' => 'critico',
        ])
        ->assertOk()
        ->assertJsonPath('data.descripcion', 'Modificado por administración')
        ->assertJsonPath('data.gravedad', 'critico');

    $this->assertDatabaseHas('eventos_seguridad', [
        'id' => $evento->id,
        'descripcion' => 'Modificado por administración',
        'gravedad' => 'critico',
    ]);
});

it('elimina un evento cuando el usuario tiene permiso para borrar', function (): void {
    $usuario = makeApiUserWithRole('administrador');
    $evento = EventoSeguridad::query()->create([
        'tipo_evento' => 'MOVIMIENTO_DETECTADO',
        'descripcion' => 'Eliminar evento',
        'gravedad' => 'informativo',
    ]);

    $this->actingAs($usuario, 'sanctum')
        ->deleteJson('/api/v1/eventos/'.$evento->id)
        ->assertNoContent();

    $this->assertDatabaseMissing('eventos_seguridad', [
        'id' => $evento->id,
    ]);
});

it('aplica la matriz de permisos por roles para eventos', function (): void {
    $administrador = makeApiUserWithRole('administrador');
    $empleado = makeApiUserWithRole('empleado');
    $usuario = makeApiUserWithRole('usuario');
    $sinPermiso = User::factory()->create(['rol' => 'usuario']);

    $eventoAdmin = EventoSeguridad::query()->create([
        'tipo_evento' => 'PUERTA_ABIERTA',
        'descripcion' => 'Evento para probar permisos de administrador',
        'gravedad' => 'advertencia',
    ]);
    $eventoEmpleado = EventoSeguridad::query()->create([
        'tipo_evento' => 'PUERTA_ABIERTA',
        'descripcion' => 'Evento para probar permisos de empleado',
        'gravedad' => 'advertencia',
    ]);
    $eventoUsuario = EventoSeguridad::query()->create([
        'tipo_evento' => 'PUERTA_ABIERTA',
        'descripcion' => 'Evento para probar permisos de usuario',
        'gravedad' => 'advertencia',
    ]);

    $this->actingAs($administrador, 'sanctum')
        ->getJson('/api/v1/eventos')
        ->assertOk();
    $this->actingAs($administrador, 'sanctum')
        ->postJson('/api/v1/eventos', [
            'tipo_evento' => 'MOVIMIENTO_DETECTADO',
            'descripcion' => 'Evento admin',
            'gravedad' => 'advertencia',
        ])
        ->assertStatus(201);
    $this->actingAs($administrador, 'sanctum')
        ->putJson('/api/v1/eventos/'.$eventoAdmin->id, ['descripcion' => 'Actualizado admin'])
        ->assertOk();
    $this->actingAs($administrador, 'sanctum')
        ->deleteJson('/api/v1/eventos/'.$eventoAdmin->id)
        ->assertNoContent();

    $this->actingAs($empleado, 'sanctum')
        ->getJson('/api/v1/eventos')
        ->assertOk();
    $this->actingAs($empleado, 'sanctum')
        ->postJson('/api/v1/eventos', [
            'tipo_evento' => 'MOVIMIENTO_DETECTADO',
            'descripcion' => 'Evento empleado',
            'gravedad' => 'informativo',
        ])
        ->assertStatus(201);
    $this->actingAs($empleado, 'sanctum')
        ->putJson('/api/v1/eventos/'.$eventoEmpleado->id, ['descripcion' => 'Actualizado empleado'])
        ->assertForbidden();
    $this->actingAs($empleado, 'sanctum')
        ->deleteJson('/api/v1/eventos/'.$eventoEmpleado->id)
        ->assertForbidden();

    $this->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/eventos')
        ->assertOk();
    $this->actingAs($usuario, 'sanctum')
        ->postJson('/api/v1/eventos', [
            'tipo_evento' => 'MOVIMIENTO_DETECTADO',
            'descripcion' => 'Evento usuario',
            'gravedad' => 'informativo',
        ])
        ->assertForbidden();
    $this->actingAs($usuario, 'sanctum')
        ->putJson('/api/v1/eventos/'.$eventoUsuario->id, ['descripcion' => 'Actualizado usuario'])
        ->assertForbidden();
    $this->actingAs($usuario, 'sanctum')
        ->deleteJson('/api/v1/eventos/'.$eventoUsuario->id)
        ->assertForbidden();

    $this->actingAs($sinPermiso, 'sanctum')
        ->getJson('/api/v1/eventos')
        ->assertForbidden();
});
