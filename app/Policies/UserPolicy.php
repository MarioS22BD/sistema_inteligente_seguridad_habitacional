<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->esAdmin() || $actor->esEmpleado();
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->esAdmin()
            || ($actor->esEmpleado() && $target->esUsuario());
    }

    public function create(User $actor, ?string $rol = null): bool
    {
        return $actor->esAdmin()
            || ($actor->esEmpleado() && $rol === 'usuario');
    }

    public function update(User $actor, User $target, ?string $nuevoRol = null): bool
    {
        return $actor->esAdmin()
            || (
                $actor->esEmpleado()
                && $target->esUsuario()
                && ($nuevoRol === null || $nuevoRol === 'usuario')
            );
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->esAdmin();
    }
}
