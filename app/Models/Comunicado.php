<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Model;

class Comunicado extends Model
{
    use Auditavel;

    public const CATEGORIAS = ['geral' => 'Geral', 'rh' => 'RH / DP', 'seguranca' => 'Segurança do trabalho', 'evento' => 'Evento', 'urgente' => 'Urgente'];

    protected $fillable = ['empresa_id', 'setor_id', 'titulo', 'corpo', 'categoria', 'fixado', 'exige_ciencia', 'publicar_em', 'expira_em', 'autor_id'];

    protected $attributes = ['categoria' => 'geral', 'fixado' => false, 'exige_ciencia' => false];

    protected $casts = ['publicar_em' => 'datetime', 'expira_em' => 'date', 'fixado' => 'boolean', 'exige_ciencia' => 'boolean'];

    public function empresa() { return $this->belongsTo(Empresa::class); }
    public function setor() { return $this->belongsTo(Setor::class); }
    public function autor() { return $this->belongsTo(User::class, 'autor_id'); }
    public function leituras() { return $this->hasMany(ComunicadoLeitura::class); }

    /** Publicados, não expirados e destinados ao usuário (pela empresa/setor do colaborador vinculado). */
    public function scopeVisiveisPara($q, User $user)
    {
        $q->where('publicar_em', '<=', now())
            ->where(fn ($w) => $w->whereNull('expira_em')->orWhere('expira_em', '>=', today()->toDateString()));
        $c = $user->colaborador;
        if ($c) {
            $q->where(fn ($w) => $w->whereNull('empresa_id')->orWhere('empresa_id', $c->empresa_id))
                ->where(fn ($w) => $w->whereNull('setor_id')->orWhere('setor_id', $c->setor_id));
        } elseif (! $user->ehGestaoPessoal()) {
            $q->whereNull('empresa_id')->whereNull('setor_id');
        }
        return $q;
    }

    public function scopeNaoLidosPor($q, User $user)
    {
        return $q->whereDoesntHave('leituras', fn ($l) => $l->where('user_id', $user->id));
    }

    /** Usuários ativos que fazem parte do público do comunicado. */
    public function publico()
    {
        return User::where('ativo', true)->whereHas('colaborador', function ($c) {
            $c->where('ativo', true);
            if ($this->empresa_id) $c->where('empresa_id', $this->empresa_id);
            if ($this->setor_id) $c->where('setor_id', $this->setor_id);
        });
    }

    public function getPublicoTextoAttribute(): string
    {
        if (! $this->empresa_id && ! $this->setor_id) return 'Todos';
        return collect([$this->empresa?->nome, $this->setor?->nome])->filter()->implode(' · ');
    }
}
