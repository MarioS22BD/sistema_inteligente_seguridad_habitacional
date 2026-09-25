<?php

namespace App\Models;

use Database\Factories\EventoSeguridadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventoSeguridad extends Model
{
    /** @use HasFactory<EventoSeguridadFactory> */
    use HasFactory;

    protected $table = 'eventos_seguridad';

    protected $fillable = [
        'tipo_evento',
        'descripcion',
        'gravedad',
    ];

    /**
     * @param  Builder<EventoSeguridad>  $consulta
     */
    public function scopeRecientes(Builder $consulta): void
    {
        $consulta->latest('created_at');
    }
}
