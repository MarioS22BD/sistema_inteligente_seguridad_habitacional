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

it('allows normal users to see automatic sensor events with no user associated', function (): void {
    $sensorEvent = EventoSeguridad::query()->create([
        'tipo_evento' => 'MOVIMIENTO_DETECTADO',
        'descripcion' => 'Sensor HC-SR04 detectó proximidad',
        'gravedad' => 'advertencia',
        'user_id' => null,
    ]);

    $user = makeApiUserWithRole('usuario');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/eventos')
        ->assertOk()
        ->assertJsonPath('data.0.id', $sensorEvent->id)
        ->assertJsonPath('data.0.tipo_evento', 'MOVIMIENTO_DETECTADO');
});

it('protects user privacy by hiding email from normal users while exposing it to administrators', function (): void {
    $author = makeApiUserWithRole('administrador');
    $event = EventoSeguridad::query()->create([
        'tipo_evento' => 'APERTURA_EMERGENCIA',
        'descripcion' => 'Apertura manual por administrador',
        'gravedad' => 'critico',
        'user_id' => $author->id,
    ]);

    $normalUser = makeApiUserWithRole('usuario');

    // Normal user: can see name and role, but NOT email
    $this->actingAs($normalUser, 'sanctum')
        ->getJson('/api/v1/eventos/'.$event->id)
        ->assertOk()
        ->assertJsonPath('data.usuario.name', $author->name)
        ->assertJsonMissingPath('data.usuario.email');

    // Administrator: can see name, role, AND email
    $this->actingAs($author, 'sanctum')
        ->getJson('/api/v1/eventos/'.$event->id)
        ->assertOk()
        ->assertJsonPath('data.usuario.name', $author->name)
        ->assertJsonPath('data.usuario.email', $author->email);
});
