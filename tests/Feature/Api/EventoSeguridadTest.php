<?php

use App\Models\EventoSeguridad;

beforeEach(fn () => seedApiRolesAndPermissions());

it('requires authentication to read security events', function (): void {
    $this->getJson('/api/v1/eventos')->assertUnauthorized();
});

it('allows a normal user to read all security events but not modify them', function (): void {
    $user = makeApiUserWithRole('usuario');
    $event = EventoSeguridad::query()->create([
        'tipo_evento' => 'PUERTA_ABIERTA',
        'descripcion' => 'Evento de seguridad',
        'gravedad' => 'advertencia',
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/eventos')
        ->assertOk()
        ->assertJsonPath('data.0.id', $event->id);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/eventos', [
            'tipo_evento' => 'PRUEBA',
            'descripcion' => 'No permitido',
            'gravedad' => 'informativo',
        ])
        ->assertForbidden();

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/eventos/'.$event->id, ['descripcion' => 'No permitido'])
        ->assertForbidden();
});

it('allows administrators to create, update, and delete events', function (): void {
    $admin = makeApiUserWithRole('administrador');

    $created = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/eventos', [
            'tipo_evento' => 'ALARMA_DISPARADA',
            'descripcion' => 'Alarma reportada por administración.',
            'gravedad' => 'critico',
        ])
        ->assertCreated()
        ->assertJsonPath('data.usuario.id', $admin->id);

    $eventId = $created->json('data.id');
    $this->actingAs($admin, 'sanctum')
        ->putJson('/api/v1/eventos/'.$eventId, ['descripcion' => 'Actualizado'])
        ->assertOk()
        ->assertJsonPath('data.descripcion', 'Actualizado');

    $this->actingAs($admin, 'sanctum')
        ->deleteJson('/api/v1/eventos/'.$eventId)
        ->assertNoContent();
});

it('rejects invalid event creation data for administrators', function (): void {
    $admin = makeApiUserWithRole('administrador');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/eventos', ['tipo_evento' => 'ALARMA_DISPARADA'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['descripcion', 'gravedad']);
});
