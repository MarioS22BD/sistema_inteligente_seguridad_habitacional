<?php

namespace App\Http\Resources;

use App\Models\EventoSeguridad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventoSeguridad
 */
class EventoSeguridadResource extends JsonResource
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
            'tipo_evento' => $this->tipo_evento,
            'descripcion' => $this->descripcion,
            'gravedad' => $this->gravedad,
            'fecha_creacion' => $this->created_at?->toISOString(),
            'usuario' => $this->when(
                $this->user_id !== null && $this->relationLoaded('user'),
                fn (): ?array => $this->user === null ? null : [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'rol' => $this->user->rol,
                ],
            ),
        ];
    }
}
