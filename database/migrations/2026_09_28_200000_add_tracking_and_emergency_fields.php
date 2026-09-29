<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos_seguridad', function (Blueprint $table): void {
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('estados_sistema', function (Blueprint $table): void {
            $table->boolean('modo_emergencia')->default(false);
            $table->boolean('servicio_puerta_activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('estados_sistema', function (Blueprint $table): void {
            $table->dropColumn(['modo_emergencia', 'servicio_puerta_activo']);
        });

        Schema::table('eventos_seguridad', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
