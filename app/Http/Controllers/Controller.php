<?php

namespace App\Http\Controllers;

use App\Models\Colaborador;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

abstract class Controller
{
    /** Colaboradores visíveis ao usuário: gestão de pessoas vê todos, gestor vê a equipe, colaborador só a si. */
    protected function colaboradoresVisiveis(User $user): Builder
    {
        $q = Colaborador::query();
        if ($user->ehGestaoPessoal()) {
            return $q;
        }
        $proprio = $user->colaborador?->id ?? 0;
        if ($user->temPerfil('gestor')) {
            return $q->where(fn ($w) => $w->where('gestor_id', $proprio)->orWhere('id', $proprio));
        }
        return $q->whereKey($proprio);
    }

    protected function autorizarColaborador(User $user, Colaborador $colaborador): void
    {
        abort_unless($this->colaboradoresVisiveis($user)->whereKey($colaborador->id)->exists(), 403);
    }
}
