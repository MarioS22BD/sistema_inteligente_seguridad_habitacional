<?php

namespace Database\Seeders;

use App\Models\EstadoSistema;
use Illuminate\Database\Seeder;

class EstadoSistemaSeeder extends Seeder
{
    /**
     * Seed the initial global system state.
     */
    public function run(): void
    {
        EstadoSistema::firstOrCreate([], [
            'esta_activado' => false,
            'puerta_abierta' => false,
            'movimiento_detectado' => false,
            'estado' => 'NORMAL',
            'descripcion_ultima_alerta' => null,
            'fecha_ultima_alerta' => null,
        ]);
    }
}
