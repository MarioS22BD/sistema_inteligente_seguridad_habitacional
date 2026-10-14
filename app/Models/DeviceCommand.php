<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $iot_device_id
 * @property int|null $requested_by
 * @property string $action
 * @property string $status
 * @property bool $is_emergency
 * @property Carbon $expires_at
 * @property Carbon|null $claimed_at
 * @property Carbon|null $confirmed_at
 * @property array<string, mixed>|null $result
 * @property string|null $error
 */
class DeviceCommand extends Model
{
    public const ACTION_OPEN_DOOR = 'abrir_puerta';

    public const STATUS_PENDING = 'pendiente';

    public const STATUS_PROCESSING = 'en_proceso';

    public const STATUS_EXECUTED = 'ejecutada';

    public const STATUS_FAILED = 'fallida';

    public const STATUS_EXPIRED = 'expirada';

    public const STATUS_CANCELLED = 'cancelada';

    protected $table = 'device_commands';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'iot_device_id',
        'requested_by',
        'action',
        'status',
        'is_emergency',
        'idempotency_key',
        'expires_at',
        'claimed_at',
        'confirmed_at',
        'result',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'is_emergency' => 'boolean',
            'expires_at' => 'datetime',
            'claimed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'result' => 'array',
        ];
    }

    /** @return BelongsTo<IotDevice, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(IotDevice::class, 'iot_device_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
