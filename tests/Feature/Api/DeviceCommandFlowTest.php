<?php

use App\Models\DeviceCommand;
use App\Models\EstadoSistema;
use App\Models\EventoSeguridad;
use App\Models\IotDevice;
use Illuminate\Support\Str;

beforeEach(fn () => seedApiRolesAndPermissions());

it('escenario 1: permite la reclamación correcta de una orden pendiente y vigente', function (): void {
    $device = provisionApiDevice();
    $admin = makeApiUserWithRole('administrador');

    $command = DeviceCommand::query()->create([
        'id' => (string) Str::uuid(),
        'iot_device_id' => $device->id,
        'requested_by' => $admin->id,
        'action' => DeviceCommand::ACTION_OPEN_DOOR,
        'status' => DeviceCommand::STATUS_PENDING,
        'is_emergency' => true,
        'expires_at' => now()->addSeconds(30),
    ]);

    $this->withHeader('X-Device-Token', 'esp8266-test-token')
        ->postJson('/api/v1/dispositivos/esp8266/ordenes/reclamar')
        ->assertOk()
        ->assertJsonPath('data.id', $command->id)
        ->assertJsonPath('data.action', DeviceCommand::ACTION_OPEN_DOOR);

    expect($command->refresh()->status)->toBe(DeviceCommand::STATUS_PROCESSING)
        ->and($command->claimed_at)->not->toBeNull();
});

it('escenario 2: descarta la reclamación de una orden expirada y la marca como tal', function (): void {
    $device = provisionApiDevice();
    $admin = makeApiUserWithRole('administrador');

    $command = DeviceCommand::query()->create([
        'id' => (string) Str::uuid(),
        'iot_device_id' => $device->id,
        'requested_by' => $admin->id,
        'action' => DeviceCommand::ACTION_OPEN_DOOR,
        'status' => DeviceCommand::STATUS_PENDING,
        'is_emergency' => true,
        'expires_at' => now()->subSecond(),
    ]);

    $this->withHeader('X-Device-Token', 'esp8266-test-token')
        ->postJson('/api/v1/dispositivos/esp8266/ordenes/reclamar')
        ->assertOk()
        ->assertJsonPath('data', null);

    expect($command->refresh()->status)->toBe(DeviceCommand::STATUS_EXPIRED);
});

it('escenario 3: garantiza reclamación atómica ante solicitudes concurrentes', function (): void {
    $device = provisionApiDevice();
    $admin = makeApiUserWithRole('administrador');

    $command = DeviceCommand::query()->create([
        'id' => (string) Str::uuid(),
        'iot_device_id' => $device->id,
        'requested_by' => $admin->id,
        'action' => DeviceCommand::ACTION_OPEN_DOOR,
        'status' => DeviceCommand::STATUS_PENDING,
        'is_emergency' => true,
        'expires_at' => now()->addSeconds(30),
    ]);

    // Primera reclamación: obtiene la orden
    $res1 = $this->withHeader('X-Device-Token', 'esp8266-test-token')
        ->postJson('/api/v1/dispositivos/esp8266/ordenes/reclamar')
        ->assertOk();
    expect($res1->json('data.id'))->toBe($command->id);

    // Segunda reclamación concurrente: no hay más órdenes pendientes
    $res2 = $this->withHeader('X-Device-Token', 'esp8266-test-token')
        ->postJson('/api/v1/dispositivos/esp8266/ordenes/reclamar')
        ->assertOk();
    expect($res2->json('data'))->toBeNull();
});

it('escenario 4: procesa confirmación exitosa y actualiza el estado lógico sin afirmar sensor físico', function (): void {
    $device = provisionApiDevice();
    $admin = makeApiUserWithRole('administrador');
    $estado = EstadoSistema::query()->create([
        'puerta_abierta' => false,
        'estado' => 'NORMAL',
    ]);

    $command = DeviceCommand::query()->create([
        'id' => (string) Str::uuid(),
        'iot_device_id' => $device->id,
        'requested_by' => $admin->id,
        'action' => DeviceCommand::ACTION_OPEN_DOOR,
        'status' => DeviceCommand::STATUS_PROCESSING,
        'claimed_at' => now(),
        'is_emergency' => true,
        'expires_at' => now()->addSeconds(30),
    ]);

    $this->withHeader('X-Device-Token', 'esp8266-test-token')
        ->postJson('/api/v1/dispositivos/esp8266/ordenes/'.$command->id.'/confirmar', [
            'ejecutada' => true,
            'puerta_abierta' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'ejecutada')
        ->assertJsonPath('data.result.servo_comandado', true)
        ->assertJsonPath('data.result.confirmacion_fisica', false);

    expect($estado->refresh()->puerta_abierta)->toBeTrue()
        ->and(EventoSeguridad::query()->where('tipo_evento', 'ORDEN_PUERTA_EJECUTADA')->count())->toBe(1);
});

it('escenario 5: procesa confirmación fallida sin alterar la puerta', function (): void {
    $device = provisionApiDevice();
    $admin = makeApiUserWithRole('administrador');
    $estado = EstadoSistema::query()->create([
        'puerta_abierta' => false,
        'estado' => 'NORMAL',
    ]);

    $command = DeviceCommand::query()->create([
        'id' => (string) Str::uuid(),
        'iot_device_id' => $device->id,
        'requested_by' => $admin->id,
        'action' => DeviceCommand::ACTION_OPEN_DOOR,
        'status' => DeviceCommand::STATUS_PROCESSING,
        'claimed_at' => now(),
        'is_emergency' => true,
        'expires_at' => now()->addSeconds(30),
    ]);

    $this->withHeader('X-Device-Token', 'esp8266-test-token')
        ->postJson('/api/v1/dispositivos/esp8266/ordenes/'.$command->id.'/confirmar', [
            'ejecutada' => false,
            'puerta_abierta' => false,
            'error' => 'Servo trabado mecánicamente',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'fallida')
        ->assertJsonPath('data.error', 'Servo trabado mecánicamente');

    expect($estado->refresh()->puerta_abierta)->toBeFalse()
        ->and(EventoSeguridad::query()->where('tipo_evento', 'ORDEN_PUERTA_FALLIDA')->count())->toBe(1);
});

it('escenario 6: confirmación duplicada es idempotente y no genera nuevos eventos', function (): void {
    $device = provisionApiDevice();
    $admin = makeApiUserWithRole('administrador');
    EstadoSistema::query()->create(['puerta_abierta' => false]);

    $command = DeviceCommand::query()->create([
        'id' => (string) Str::uuid(),
        'iot_device_id' => $device->id,
        'requested_by' => $admin->id,
        'action' => DeviceCommand::ACTION_OPEN_DOOR,
        'status' => DeviceCommand::STATUS_PROCESSING,
        'claimed_at' => now(),
        'is_emergency' => true,
        'expires_at' => now()->addSeconds(30),
    ]);

    $payload = [
        'ejecutada' => true,
        'puerta_abierta' => true,
    ];

    // Primera confirmación
    $this->withHeader('X-Device-Token', 'esp8266-test-token')
        ->postJson('/api/v1/dispositivos/esp8266/ordenes/'.$command->id.'/confirmar', $payload)
        ->assertOk()
        ->assertJsonPath('data.status', 'ejecutada');

    expect(EventoSeguridad::query()->where('tipo_evento', 'ORDEN_PUERTA_EJECUTADA')->count())->toBe(1);

    // Segunda confirmación duplicada
    $this->withHeader('X-Device-Token', 'esp8266-test-token')
        ->postJson('/api/v1/dispositivos/esp8266/ordenes/'.$command->id.'/confirmar', $payload)
        ->assertOk()
        ->assertJsonPath('data.status', 'ejecutada');

    // Sigue habiendo un solo evento
    expect(EventoSeguridad::query()->where('tipo_evento', 'ORDEN_PUERTA_EJECUTADA')->count())->toBe(1);
});

it('escenario 7: reconexión del dispositivo no re-ejecuta órdenes previas', function (): void {
    $device = provisionApiDevice();
    $admin = makeApiUserWithRole('administrador');

    $command = DeviceCommand::query()->create([
        'id' => (string) Str::uuid(),
        'iot_device_id' => $device->id,
        'requested_by' => $admin->id,
        'action' => DeviceCommand::ACTION_OPEN_DOOR,
        'status' => DeviceCommand::STATUS_EXECUTED,
        'claimed_at' => now()->subMinutes(5),
        'confirmed_at' => now()->subMinutes(4),
        'is_emergency' => true,
        'expires_at' => now()->addMinutes(10),
    ]);

    // Dispositivo reconecta y pide órdenes pendientes
    $this->withHeader('X-Device-Token', 'esp8266-test-token')
        ->postJson('/api/v1/dispositivos/esp8266/ordenes/reclamar')
        ->assertOk()
        ->assertJsonPath('data', null);

    expect($command->refresh()->status)->toBe(DeviceCommand::STATUS_EXECUTED);
});

it('escenario 8: rechaza el intento de confirmar una orden perteneciente a otro dispositivo', function (): void {
    $device1 = provisionApiDevice('token-device-1');
    $device2 = IotDevice::query()->create([
        'name' => 'esp8266-secondary',
        'api_token_hash' => hash('sha256', 'token-device-2'),
        'is_active' => true,
    ]);

    $admin = makeApiUserWithRole('administrador');

    $command = DeviceCommand::query()->create([
        'id' => (string) Str::uuid(),
        'iot_device_id' => $device1->id,
        'requested_by' => $admin->id,
        'action' => DeviceCommand::ACTION_OPEN_DOOR,
        'status' => DeviceCommand::STATUS_PROCESSING,
        'claimed_at' => now(),
        'is_emergency' => true,
        'expires_at' => now()->addSeconds(30),
    ]);

    // Device 2 intenta confirmar la orden del Device 1
    $this->withHeader('X-Device-Token', 'token-device-2')
        ->postJson('/api/v1/dispositivos/esp8266/ordenes/'.$command->id.'/confirmar', [
            'ejecutada' => true,
            'puerta_abierta' => true,
        ])
        ->assertForbidden(); // deviceFromRequest aborts if device name != esp8266 or device mismatch
});

it('escenario 9: detener servicio de puerta cancela órdenes pendientes ordinarias e impide su reclamación', function (): void {
    $device = provisionApiDevice();
    $admin = makeApiUserWithRole('administrador');

    $estado = EstadoSistema::query()->create([
        'servicio_puerta_activo' => true,
        'estado' => 'NORMAL',
    ]);

    // Orden ordinaria (no de emergencia)
    $ordenOrdinaria = DeviceCommand::query()->create([
        'id' => (string) Str::uuid(),
        'iot_device_id' => $device->id,
        'requested_by' => $admin->id,
        'action' => DeviceCommand::ACTION_OPEN_DOOR,
        'status' => DeviceCommand::STATUS_PENDING,
        'is_emergency' => false,
        'expires_at' => now()->addSeconds(30),
    ]);

    // Detener servicio de puerta
    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/sistema/toggle-servicio-puerta')
        ->assertOk()
        ->assertJsonPath('data.servicio_puerta_activo', false);

    expect($ordenOrdinaria->refresh()->status)->toBe(DeviceCommand::STATUS_CANCELLED);

    // Dispositivo intenta reclamar: no debe obtener la orden cancelada
    $this->withHeader('X-Device-Token', 'esp8266-test-token')
        ->postJson('/api/v1/dispositivos/esp8266/ordenes/reclamar')
        ->assertOk()
        ->assertJsonPath('data', null);

    // Sin embargo, una orden de emergencia autorizada SÍ puede ser creada y reclamada
    $ordenEmergencia = DeviceCommand::query()->create([
        'id' => (string) Str::uuid(),
        'iot_device_id' => $device->id,
        'requested_by' => $admin->id,
        'action' => DeviceCommand::ACTION_OPEN_DOOR,
        'status' => DeviceCommand::STATUS_PENDING,
        'is_emergency' => true,
        'expires_at' => now()->addSeconds(30),
    ]);

    $this->withHeader('X-Device-Token', 'esp8266-test-token')
        ->postJson('/api/v1/dispositivos/esp8266/ordenes/reclamar')
        ->assertOk()
        ->assertJsonPath('data.id', $ordenEmergencia->id);

    // Reactivar el servicio no resucita órdenes canceladas
    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/sistema/toggle-servicio-puerta')
        ->assertOk()
        ->assertJsonPath('data.servicio_puerta_activo', true);

    expect($ordenOrdinaria->refresh()->status)->toBe(DeviceCommand::STATUS_CANCELLED);
});

it('escenario 10: inicializa automáticamente el estado del sistema si la tabla está vacía', function (): void {
    EstadoSistema::query()->delete();
    expect(EstadoSistema::query()->count())->toBe(0);

    $user = makeApiUserWithRole('usuario');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/estado-sistema')
        ->assertOk()
        ->assertJsonPath('data.estado', 'NORMAL')
        ->assertJsonPath('data.servicio_puerta_activo', true);

    expect(EstadoSistema::query()->count())->toBe(1);
});
