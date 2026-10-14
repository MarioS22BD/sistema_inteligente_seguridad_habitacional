<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estados_sistema', function (Blueprint $table): void {
            $table->decimal('distancia_cm', 7, 2)->nullable();
        });

        Schema::create('iot_devices', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->char('api_token_hash', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('device_commands', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('iot_device_id')->constrained('iot_devices')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('status', 20)->default('pendiente')->index();
            $table->boolean('is_emergency')->default(false);
            $table->string('idempotency_key', 80)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['iot_device_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_commands');
        Schema::dropIfExists('iot_devices');

        Schema::table('estados_sistema', function (Blueprint $table): void {
            $table->dropColumn('distancia_cm');
        });
    }
};
