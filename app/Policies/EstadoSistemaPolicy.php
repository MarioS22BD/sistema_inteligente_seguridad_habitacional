<?php

namespace App\Policies;

use App\Models\EstadoSistema;
use App\Models\User;

class EstadoSistemaPolicy
{
    public function operate(User $user): bool
    {
        return $user->esAdmin() || $user->esEmpleado();
    }

    public function viewAny(User $user): bool
    {
        return $user->can('ver_estado_sistema');
    }

    public function view(User $user, EstadoSistema $estado): bool
    {
        return $user->can('ver_estado_sistema');
    }
}
