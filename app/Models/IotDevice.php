<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $api_token_hash
 * @property bool $is_active
 * @property Carbon|null $last_seen_at
 */
class IotDevice extends Model
{
    protected $table = 'iot_devices';

    protected $fillable = [
        'name',
        'api_token_hash',
        'is_active',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    /** @return HasMany<DeviceCommand, $this> */
    public function commands(): HasMany
    {
        return $this->hasMany(DeviceCommand::class);
    }
}
