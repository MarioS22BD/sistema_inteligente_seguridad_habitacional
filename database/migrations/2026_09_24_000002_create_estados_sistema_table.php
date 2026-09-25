<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('estados_sistema', function (Blueprint $table) {
            $table->id();
            $table->boolean('esta_activado')->default(false);
            $table->boolean('puerta_abierta')->default(false);
            $table->boolean('movimiento_detectado')->default(false);
            $table->string('estado')->default('NORMAL');
            $table->string('descripcion_ultima_alerta')->nullable();
            $table->timestamp('fecha_ultima_alerta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estados_sistema');
    }
};
