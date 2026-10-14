<?php

use App\Models\DeviceCommand;
use App\Models\EstadoSistema;
use App\Models\EventoSeguridad;

beforeEach(fn () => seedApiRolesAndPermissions());

it('requires authentication and an administrator for emergency commands', function (): void {
    provisionApiDevice();
    $this->postJson('/api/v1/sistema/apertura-emergencia', [], ['Idempotency-Key' => 'request-key-001'])
        ->assertUnauthorized();
    $this->postJson('/api/v1/sistema/toggle-servicio-puerta')->assertUnauthorized();

    $user = makeApiUserWithRole('usuario');
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/sistema/apertura-emergencia', [], ['Idempotency-Key' => 'request-key-002'])
        ->assertForbidden();
});

it('creates an idempotent pending emergency command without claiming the door opened', function (): void {
    provisionApiDevice();
    $admin = makeApiUserWithRole('administrador');
    $state = EstadoSistema::query()->create(['estado' => 'NORMAL']);
    $headers = ['Idempotency-Key' => 'request-key-003'];

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/sistema/apertura-emergencia', [], $headers)
        ->assertAccepted()
        ->assertJsonPath('data.status', 'pendiente')
        ->assertJsonPath('data.action', 'abrir_puerta');

    $commandId = $response->json('data.id');
    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/sistema/apertura-emergencia', [], $headers)
        ->assertAccepted()
        ->assertJsonPath('data.id', $commandId);

    expect($state->refresh()->puerta_abierta)->toBeFalse()
        ->and(DeviceCommand::query()->count())->toBe(1);
});

it('delivers a pending order to its ESP8266 only once and records electronic confirmation', function (): void {
    $device = provisionApiDevice();
    $admin = makeApiUserWithRole('administrador');
    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/sistema/apertura-emergencia', [], ['Idempotency-Key' => 'request-key-004'])
        ->assertAccepted();
    $command = DeviceCommand::query()->firstOrFail();

    $this->withHeader('X-Device-Token', 'esp8266-test-token')
        ->postJson('/api/v1/dispositivos/esp8266/ordenes/reclamar')
        ->assertOk()
        ->assertJsonPath('data.id', $command->id)
        ->assertJsonPath('data.action', 'abrir_puerta');

    $this->withHeader('X-Device-Token', 'esp8266-test-token')
        ->postJson('/api/v1/dispositivos/esp8266/ordenes/reclamar')
        ->assertOk()
        ->assertJsonPath('data', null);

    $confirmation = [
        'ejecutada' => true,
        'puerta_abierta' => true,
    ];
    $this->withHeader('X-Device-Token', 'esp8266-test-token')
        ->postJson('/api/v1/dispositivos/esp8266/ordenes/'.$command->id.'/confirmar', $confirmation)
        ->assertOk()
        ->assertJsonPath('data.status', 'ejecutada')
        ->assertJsonPath('data.result.confirmacion_fisica', false);

    $this->withHeader('X-Device-Token', 'esp8266-test-token')
        ->postJson('/api/v1/dispositivos/esp8266/ordenes/'.$command->id.'/confirmar', $confirmation)
        ->assertOk()
        ->assertJsonPath('data.status', 'ejecutada');

    $this->assertDatabaseHas('estados_sistema', ['puerta_abierta' => true]);
    expect(EventoSeguridad::query()->where('tipo_evento', 'ORDEN_PUERTA_EJECUTADA')->count())->toBe(1);
});

it('rejects orders owned by another device and marks expired orders without delivery', function (): void {
    provisionApiDevice();
    $admin = makeApiUserWithRole('administrador');
    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/sistema/apertura-emergencia', [], ['Idempotency-Key' => 'request-key-005'])
        ->assertAccepted();
    $command = DeviceCommand::query()->firstOrFail();
    $command->forceFill(['expires_at' => now()->subSecond()])->save();

    $this->withHeader('X-Device-Token', 'esp8266-test-token')
        ->postJson('/api/v1/dispositivos/esp8266/ordenes/reclamar')
        ->assertOk()
        ->assertJsonPath('data', null);
    expect($command->refresh()->status)->toBe(DeviceCommand::STATUS_EXPIRED);
});

it('records a failed device execution and exposes the result to authorized users', function (): void {
    provisionApiDevice();
    $admin = makeApiUserWithRole('administrador');
    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/sistema/apertura-emergencia', [], ['Idempotency-Key' => 'request-key-006'])
        ->assertAccepted();
    $command = DeviceCommand::query()->firstOrFail();

    $this->withHeader('X-Device-Token', 'esp8266-test-token')
        ->postJson('/api/v1/dispositivos/esp8266/ordenes/reclamar')
        ->assertOk();
    $this->withHeader('X-Device-Token', 'esp8266-test-token')
        ->postJson('/api/v1/dispositivos/esp8266/ordenes/'.$command->id.'/confirmar', [
            'ejecutada' => false,
            'puerta_abierta' => false,
            'error' => 'Fallo del servo',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'fallida')
        ->assertJsonPath('data.error', 'Fallo del servo');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/sistema/ordenes/'.$command->id)
        ->assertOk()
        ->assertJsonPath('data.status', 'fallida');
});

it('toggles the door service only for administrators and records transitions', function (): void {
    $admin = makeApiUserWithRole('administrador');
    EstadoSistema::query()->create(['servicio_puerta_activo' => true]);

    $user = makeApiUserWithRole('usuario');
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/sistema/toggle-servicio-puerta')
        ->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/sistema/toggle-servicio-puerta')
        ->assertOk()
        ->assertJsonPath('data.servicio_puerta_activo', false);
    expect(EventoSeguridad::query()->where('user_id', $admin->id)->count())->toBe(1);
});
