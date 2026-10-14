<?php

use App\Models\EstadoSistema;
use App\Models\EventoSeguridad;

beforeEach(fn () => seedApiRolesAndPermissions());

it('does not expose the former public security state update route', function (): void {
    $this->postJson('/api/v1/seguridad/actualizar', [
        'esta_activado' => true,
        'puerta_abierta' => true,
        'movimiento_detectado' => true,
    ])->assertNotFound();

    $this->assertDatabaseCount('estados_sistema', 0);
});

it('rejects invalid, missing, and query-string device credentials', function (): void {
    provisionApiDevice('correct-device-token');

    $payload = [
        'puerta_abierta' => false,
        'movimiento_detectado' => false,
    ];

    $this->postJson('/api/v1/dispositivos/esp8266/telemetria', $payload)
        ->assertUnauthorized();

    $this->withHeader('X-Device-Token', 'wrong-token')
        ->postJson('/api/v1/dispositivos/esp8266/telemetria', $payload)
        ->assertUnauthorized();

    $this->postJson('/api/v1/dispositivos/esp8266/telemetria?token=correct-device-token', $payload)
        ->assertUnauthorized();
});

it('accepts ESP8266 sensor reports without allowing it to change alarm arming', function (): void {
    $device = provisionApiDevice();
    $state = EstadoSistema::query()->create([
        'esta_activado' => true,
        'puerta_abierta' => false,
        'movimiento_detectado' => false,
        'estado' => 'NORMAL',
    ]);

    $this->withHeader('X-Device-Token', 'esp8266-test-token')
        ->postJson('/api/v1/dispositivos/esp8266/telemetria', [
            'esta_activado' => false,
            'puerta_abierta' => true,
            'movimiento_detectado' => true,
            'distancia_cm' => 32.5,
        ])
        ->assertOk();

    expect($state->refresh()->esta_activado)->toBeTrue()
        ->and($state->puerta_abierta)->toBeTrue()
        ->and($state->distancia_cm)->toBe('32.50')
        ->and($device->refresh()->last_seen_at)->not->toBeNull();

    $this->assertDatabaseHas('eventos_seguridad', [
        'tipo_evento' => 'PROXIMIDAD_DETECTADA',
        'gravedad' => 'advertencia',
    ]);
    $this->assertDatabaseHas('eventos_seguridad', [
        'tipo_evento' => 'ALARMA_DISPARADA',
        'gravedad' => 'critico',
    ]);
});

it('validates required sensor fields on device telemetry', function (): void {
    provisionApiDevice();

    $this->withHeader('X-Device-Token', 'esp8266-test-token')
        ->postJson('/api/v1/dispositivos/esp8266/telemetria', [
            'puerta_abierta' => false,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('movimiento_detectado');
});

it('requires user authentication and administrator authorization to arm the alarm', function (): void {
    $this->putJson('/api/v1/sistema/armado', ['esta_activado' => true])
        ->assertUnauthorized();

    $user = makeApiUserWithRole('usuario');
    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/sistema/armado', ['esta_activado' => true])
        ->assertForbidden();

    $admin = makeApiUserWithRole('administrador');
    $this->actingAs($admin, 'sanctum')
        ->putJson('/api/v1/sistema/armado', ['esta_activado' => true])
        ->assertOk()
        ->assertJsonPath('data.esta_activado', true);

    $this->assertDatabaseHas('eventos_seguridad', [
        'tipo_evento' => 'ALARMA_ARMADA',
        'user_id' => $admin->id,
    ]);
});
