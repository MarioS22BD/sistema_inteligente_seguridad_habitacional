<?php

beforeEach(fn () => seedApiRolesAndPermissions());

it('requires an authenticated session and an approved local IP to authorize the camera proxy', function (): void {
    config()->set('camera.allowed_cidrs', ['192.168.1.0/24']);

    $this->get('/camera/authorize', ['X-Forwarded-For' => '192.168.1.30'])
        ->assertUnauthorized();

    $user = makeApiUserWithRole('usuario');
    $this->actingAs($user)
        ->get('/camera/authorize', ['X-Forwarded-For' => '192.168.1.30'])
        ->assertNoContent();

    $this->actingAs($user)
        ->get('/camera/authorize', [
            'X-Forwarded-For' => '203.0.113.10',
            'X-Real-IP' => '192.168.1.30',
        ])
        ->assertForbidden();
});

it('denies camera proxy authorization when no local network allowlist is configured', function (): void {
    config()->set('camera.allowed_cidrs', []);
    $admin = makeApiUserWithRole('administrador');

    $this->actingAs($admin)
        ->get('/camera/authorize', ['X-Forwarded-For' => '192.168.1.30'])
        ->assertForbidden();
});
