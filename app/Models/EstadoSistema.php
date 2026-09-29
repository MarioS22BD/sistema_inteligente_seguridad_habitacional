<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstadoSistema extends Model
{
    protected $table = 'estados_sistema';

    protected $fillable = [
        'esta_activado',
        'puerta_abierta',
        'movimiento_detectado',
        'estado',
        'descripcion_ultima_alerta',
        'fecha_ultima_alerta',
        'modo_emergencia',
        'servicio_puerta_activo',
    ];

    protected function casts(): array
    {
        return [
            'esta_activado' => 'boolean',
            'puerta_abierta' => 'boolean',
            'movimiento_detectado' => 'boolean',
            'modo_emergencia' => 'boolean',
            'servicio_puerta_activo' => 'boolean',
            'fecha_ultima_alerta' => 'datetime',
        ];
    }
}
