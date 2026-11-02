<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Auditavel, HasFactory, Notifiable;

    public const PERFIS = [
        'admin' => 'Administrador',
        'rh' => 'Recursos Humanos',
        'dp' => 'Departamento Pessoal',
        'gestor' => 'Gestor',
        'colaborador' => 'Colaborador',
    ];

    protected $fillable = ['name', 'email', 'password', 'perfil', 'ativo'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ativo' => 'boolean',
        ];
    }

    public function colaborador()
    {
        return $this->hasOne(Colaborador::class);
    }

    public function temPerfil(string ...$perfis): bool
    {
        return in_array($this->perfil, $perfis, true);
    }

    /** Pode administrar cadastros e o ponto de qualquer colaborador. */
    public function ehGestaoPessoal(): bool
    {
        return $this->temPerfil('admin', 'rh', 'dp');
    }

    /** Aprova solicitações e férias: RH/DP/admin para todos; gestor para a equipe direta. Ninguém aprova o próprio pedido. */
    public function podeAprovarPara(Colaborador $colaborador): bool
    {
        if ($colaborador->user_id === $this->id) {
            return false;
        }
        if ($this->ehGestaoPessoal()) {
            return true;
        }
        return $this->temPerfil('gestor') && $this->colaborador && $colaborador->gestor_id === $this->colaborador->id;
    }

    /** Colaboradores cujos pedidos este usuário pode decidir. */
    public function aprovaveis()
    {
        $q = Colaborador::query()->where(fn ($w) => $w->whereNull('user_id')->orWhere('user_id', '!=', $this->id));
        if ($this->ehGestaoPessoal()) {
            return $q;
        }
        if ($this->temPerfil('gestor') && $this->colaborador) {
            return $q->where('gestor_id', $this->colaborador->id);
        }
        return $q->whereRaw('1 = 0');
    }

    public function aprova(): bool
    {
        return $this->ehGestaoPessoal() || $this->temPerfil('gestor');
    }
}
