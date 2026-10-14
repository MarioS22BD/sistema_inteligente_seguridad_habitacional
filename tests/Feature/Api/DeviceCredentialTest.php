<?php

use App\Models\IotDevice;

it('stores only a hash when rotating a device credential and supports revocation', function (): void {
    $this->artisan('device:token esp8266')
        ->assertSuccessful()
        ->expectsOutputToContain('Credential rotated.');

    $device = IotDevice::query()->where('name', 'esp8266')->firstOrFail();
    expect(strlen($device->api_token_hash))->toBe(64)
        ->and($device->api_token_hash)->not->toBe('');

    $this->artisan('device:token esp8266 --revoke')
        ->assertSuccessful()
        ->expectsOutputToContain('revoked');

    expect($device->refresh()->is_active)->toBeFalse();
});
