<?php

namespace App\Policies;

use App\Models\EstadoSistema;
use App\Models\User;

class EstadoSistemaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ver_estado_sistema');
    }

    public function view(User $user, EstadoSistema $estado): bool
    {
        return $user->can('ver_estado_sistema');
    }
}
