<?php

namespace Database\Factories;

use App\Models\EventoSeguridad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventoSeguridad>
 */
class EventoSeguridadFactory extends Factory
{
    protected $model = EventoSeguridad::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, string>
     */
    public function definition(): array
    {
        $tipos = [
            'PUERTA_ABIERTA',
            'PUERTA_CERRADA',
            'MOVIMIENTO_DETECTADO',
            'SISTEMA_ACTIVADO',
            'SISTEMA_DESACTIVADO',
            'ALARMA_DISPARADA',
        ];

        $descripciones = [
            'La puerta de la habitación fue abierta.',
            'La puerta de la habitación fue cerrada.',
            'Se detectó movimiento dentro de la habitación.',
            'El sistema de seguridad fue activado.',
            'El sistema de seguridad fue desactivado.',
            'Se disparó una alarma de seguridad.',
        ];

        return [
            'tipo_evento' => $this->faker->randomElement($tipos),
            'descripcion' => $this->faker->randomElement($descripciones),
            'gravedad' => $this->faker->randomElement([
                'informativo',
                'advertencia',
                'critico',
            ]),
        ];
    }
}
