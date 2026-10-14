<?php

use App\Models\User;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Support\Facades\Hash;

it('does not overwrite passwords or role data of existing seeded accounts', function (): void {
    $passwordHash = Hash::make('a-unique-existing-password');
    $existing = User::factory()->create([
        'email' => 'admin@seguridad.local',
        'rol' => 'usuario',
        'password' => $passwordHash,
        'email_verified_at' => null,
    ]);

    app(UsuarioSeeder::class)->run();

    expect($existing->refresh()->password)->toBe($passwordHash)
        ->and($existing->rol)->toBe('usuario')
        ->and($existing->email_verified_at)->toBeNull();
});
