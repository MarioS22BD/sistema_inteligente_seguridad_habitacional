<?php

namespace App\Http\Resources;

use App\Models\EstadoSistema;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin EstadoSistema
 */
class EstadoSistemaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'esta_activado' => $this->esta_activado,
            'puerta_abierta' => $this->puerta_abierta,
            'movimiento_detectado' => $this->movimiento_detectado,
            'estado' => $this->estado,
            'descripcion_ultima_alerta' => $this->descripcion_ultima_alerta,
            'fecha_ultima_alerta' => $this->fecha_ultima_alerta === null
                ? null
                : Carbon::parse($this->fecha_ultima_alerta)->toISOString(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
