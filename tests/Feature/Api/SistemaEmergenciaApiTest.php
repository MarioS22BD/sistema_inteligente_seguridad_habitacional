<?php

use App\Models\EstadoSistema;
use App\Models\EventoSeguridad;

beforeEach(fn () => seedApiRolesAndPermissions());

it('requires authentication for emergency door commands', function (): void {
    $this->postJson('/api/v1/sistema/apertura-emergencia')->assertUnauthorized();
    $this->postJson('/api/v1/sistema/toggle-servicio-puerta')->assertUnauthorized();
});

it('allows an employee to request emergency opening and records the actor', function (): void {
    $employee = makeApiUserWithRole('empleado');
    EstadoSistema::query()->create(['estado' => 'NORMAL']);

    $this->actingAs($employee, 'sanctum')
        ->postJson('/api/v1/sistema/apertura-emergencia')
        ->assertOk()
        ->assertJsonPath('data.puerta_abierta', true)
        ->assertJsonPath('data.modo_emergencia', true)
        ->assertJsonPath('data.estado', 'ALARMA');

    $this->assertDatabaseHas('eventos_seguridad', [
        'tipo_evento' => 'APERTURA_EMERGENCIA',
        'gravedad' => 'critico',
        'user_id' => $employee->id,
    ]);
});

it('denies emergency controls to normal users', function (): void {
    $user = makeApiUserWithRole('usuario');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/sistema/apertura-emergencia')
        ->assertForbidden();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/sistema/toggle-servicio-puerta')
        ->assertForbidden();
});

it('toggles the door service and records each transition', function (): void {
    $admin = makeApiUserWithRole('administrador');
    EstadoSistema::query()->create(['servicio_puerta_activo' => true]);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/sistema/toggle-servicio-puerta')
        ->assertOk()
        ->assertJsonPath('data.servicio_puerta_activo', false);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/sistema/toggle-servicio-puerta')
        ->assertOk()
        ->assertJsonPath('data.servicio_puerta_activo', true);

    expect(EventoSeguridad::query()->where('user_id', $admin->id)->count())->toBe(2);
});

it('sets the event actor to null when that user is deleted', function (): void {
    $admin = makeApiUserWithRole('administrador');
    $event = EventoSeguridad::query()->create([
        'tipo_evento' => 'APERTURA_EMERGENCIA',
        'descripcion' => 'Evento con actor',
        'gravedad' => 'critico',
        'user_id' => $admin->id,
    ]);

    $admin->delete();

    expect($event->refresh()->user_id)->toBeNull();
});
