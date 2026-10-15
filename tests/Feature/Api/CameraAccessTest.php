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

it('protects /camera/health and returns JSON status for authorized users', function (): void {
    config()->set('camera.allowed_cidrs', ['127.0.0.1/32', '192.168.1.0/24']);

    $this->get('/camera/health')->assertUnauthorized();

    $user = makeApiUserWithRole('usuario');

    $this->actingAs($user)
        ->get('/camera/health', ['X-Forwarded-For' => '203.0.113.5'])
        ->assertForbidden();

    $response = $this->actingAs($user)
        ->get('/camera/health', ['X-Forwarded-For' => '192.168.1.30']);

    expect(in_array($response->status(), [200, 503], true))->toBeTrue()
        ->and($response->json('camera_host'))->not->toBeNull();
});

it('protects /camera/stream and returns reverse proxy guidance for direct PHP access', function (): void {
    config()->set('camera.allowed_cidrs', ['127.0.0.1/32', '192.168.1.0/24']);

    $this->get('/camera/stream')->assertUnauthorized();

    $user = makeApiUserWithRole('usuario');

    $this->actingAs($user)
        ->get('/camera/stream', ['X-Forwarded-For' => '203.0.113.5'])
        ->assertForbidden();

    $this->actingAs($user)
        ->get('/camera/stream', ['X-Forwarded-For' => '192.168.1.30'])
        ->assertStatus(503)
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8');
});
