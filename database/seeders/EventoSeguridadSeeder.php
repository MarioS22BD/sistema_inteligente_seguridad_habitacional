<?php

namespace Database\Seeders;

use App\Models\EventoSeguridad;
use Illuminate\Database\Seeder;

class EventoSeguridadSeeder extends Seeder
{
    /**
     * Seed sample security events.
     */
    public function run(): void
    {
        EventoSeguridad::factory()->count(20)->create();
    }
}
