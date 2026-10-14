<?php

namespace App\Policies;

use App\Models\EventoSeguridad;
use App\Models\User;

class EventoSeguridadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ver_eventos');
    }

    public function view(User $user, EventoSeguridad $evento): bool
    {
        return $user->can('ver_eventos');
    }

    public function create(User $user): bool
    {
        return $user->esAdmin();
    }

    public function update(User $user, EventoSeguridad $evento): bool
    {
        return $user->esAdmin();
    }

    public function delete(User $user, EventoSeguridad $evento): bool
    {
        return $user->esAdmin();
    }
}
