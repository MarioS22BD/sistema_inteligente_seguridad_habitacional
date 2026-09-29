<?php

namespace App\Models;

use Database\Factories\EventoSeguridadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoSeguridad extends Model
{
    /** @use HasFactory<EventoSeguridadFactory> */
    use HasFactory;

    protected $table = 'eventos_seguridad';

    protected $fillable = [
        'tipo_evento',
        'descripcion',
        'gravedad',
        'user_id',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<EventoSeguridad>  $consulta
     */
    public function scopeRecientes(Builder $consulta): void
    {
        $consulta->latest('created_at');
    }
}
