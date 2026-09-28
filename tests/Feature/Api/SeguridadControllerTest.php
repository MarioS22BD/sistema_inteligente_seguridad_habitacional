<?php

use App\Models\EstadoSistema;

beforeEach(fn () => seedApiRolesAndPermissions());

it('actualiza el estado del sistema desde el ESP32 con respuesta exitosa', function (): void {
    $response = $this->postJson('/api/v1/seguridad/actualizar', [
        'esta_activado' => true,
        'puerta_abierta' => true,
        'movimiento_detectado' => false,
    ]);

    $response
        ->assertOk()
        ->assertJson([
            'estado' => 'exito',
            'mensaje' => 'Estado actualizado correctamente',
        ]);

    $this->assertDatabaseHas('estados_sistema', [
        'esta_activado' => true,
        'puerta_abierta' => true,
        'movimiento_detectado' => false,
        'estado' => 'ALARMA',
    ]);

    $this->assertDatabaseHas('eventos_seguridad', [
        'tipo_evento' => 'PUERTA_ABIERTA',
        'gravedad' => 'advertencia',
    ]);
});

it('valida los campos obligatorios de la actualización del ESP32', function (): void {
    $this->postJson('/api/v1/seguridad/actualizar', [
        'esta_activado' => true,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['puerta_abierta', 'movimiento_detectado']);
});

it('crea una alarma cuando la puerta y el movimiento se detectan mientras el sistema está activado', function (): void {
    EstadoSistema::query()->create([
        'esta_activado' => true,
        'puerta_abierta' => false,
        'movimiento_detectado' => false,
        'estado' => 'NORMAL',
    ]);

    $this->postJson('/api/v1/seguridad/actualizar', [
        'esta_activado' => true,
        'puerta_abierta' => true,
        'movimiento_detectado' => true,
    ])->assertOk();

    $this->assertDatabaseHas('eventos_seguridad', [
        'tipo_evento' => 'PUERTA_ABIERTA',
        'gravedad' => 'advertencia',
    ]);

    $this->assertDatabaseHas('eventos_seguridad', [
        'tipo_evento' => 'MOVIMIENTO_DETECTADO',
        'gravedad' => 'advertencia',
    ]);

    $this->assertDatabaseHas('eventos_seguridad', [
        'tipo_evento' => 'ALARMA_DISPARADA',
        'gravedad' => 'critico',
    ]);
});
