<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->esAdmin();
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->esAdmin();
    }

    public function create(User $actor, ?string $rol = null): bool
    {
        return $actor->esAdmin();
    }

    public function update(User $actor, User $target, ?string $nuevoRol = null): bool
    {
        return $actor->esAdmin();
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->esAdmin();
    }
}
