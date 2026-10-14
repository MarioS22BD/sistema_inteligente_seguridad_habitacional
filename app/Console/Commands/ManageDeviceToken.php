<?php

namespace App\Console\Commands;

use App\Models\IotDevice;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ManageDeviceToken extends Command
{
    protected $signature = 'device:token {name} {--revoke : Revoke the current device credential}';

    protected $description = 'Rotate or revoke an IoT device API credential';

    public function handle(): int
    {
        $name = (string) $this->argument('name');

        if (! preg_match('/\A[a-z0-9-]{1,64}\z/', $name)) {
            $this->error('Device name must contain only lowercase letters, digits, and hyphens.');

            return self::FAILURE;
        }

        $device = IotDevice::query()->firstWhere('name', $name);

        if ($this->option('revoke')) {
            if (! $device) {
                $this->error('Device does not exist.');

                return self::FAILURE;
            }

            $device->forceFill(['is_active' => false])->save();
            $this->info('Device credential revoked.');

            return self::SUCCESS;
        }

        $token = Str::random(64);
        $device = IotDevice::query()->updateOrCreate(
            ['name' => $name],
            [
                'api_token_hash' => hash('sha256', $token),
                'is_active' => true,
            ],
        );

        $this->info('Credential rotated. Copy it to the device now; it will not be shown again.');
        $this->line($token);

        return self::SUCCESS;
    }
}
