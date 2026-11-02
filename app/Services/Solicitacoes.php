<?php

namespace App\Services;

use App\Models\PeriodoFechamento;
use App\Models\Solicitacao;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Decisão das solicitações do portal. Ajuste aprovado vira marcação (origem "ajuste");
 * abono e atestado aprovados passam a valer na apuração do espelho.
 */
class Solicitacoes
{
    public function __construct(private RegistroPonto $registro) {}

    public function aprovar(Solicitacao $s, User $decisor, ?string $resposta = null): Solicitacao
    {
        $this->validarDecisao($s, $decisor);
        $this->validarPeriodoAberto($s);

        return DB::transaction(function () use ($s, $decisor, $resposta) {
            $marcacaoId = null;
            if ($s->tipo === 'ajuste') {
                $quando = Carbon::parse($s->data->toDateString().' '.substr($s->hora, 0, 5));
                if ($quando->isFuture()) {
                    throw new DomainException('Não é possível incluir marcação no futuro.');
                }
                $marcacaoId = $this->registro->registrar($s->colaborador, $quando, [
                    'origem' => 'ajuste', 'motivo' => "Solicitação nº {$s->id}: ".mb_substr($s->motivo, 0, 200),
                    'criado_por' => $decisor->id, 'ip' => request()?->ip(),
                ])->id;
            }
            $s->update([
                'status' => 'aprovada', 'resposta' => $resposta, 'decidido_por' => $decisor->id,
                'decidido_em' => now(), 'marcacao_id' => $marcacaoId,
            ]);
            return $s;
        });
    }

    public function rejeitar(Solicitacao $s, User $decisor, string $resposta): Solicitacao
    {
        $this->validarDecisao($s, $decisor);
        $s->update(['status' => 'rejeitada', 'resposta' => $resposta, 'decidido_por' => $decisor->id, 'decidido_em' => now()]);
        return $s;
    }

    /** Cancela: o próprio colaborador enquanto pendente; RH/DP também desfaz abono/atestado já aprovado em período aberto. */
    public function cancelar(Solicitacao $s, User $user): Solicitacao
    {
        $proprio = $s->colaborador->user_id === $user->id;
        if ($s->status === 'pendente' && ($proprio || $user->podeAprovarPara($s->colaborador) || $user->ehGestaoPessoal())) {
            $s->update(['status' => 'cancelada', 'decidido_por' => $user->id, 'decidido_em' => now()]);
            return $s;
        }
        if ($s->status === 'aprovada' && $s->tipo !== 'ajuste' && $user->ehGestaoPessoal()) {
            $this->validarPeriodoAberto($s);
            $s->update(['status' => 'cancelada', 'resposta' => trim(($s->resposta ? $s->resposta.' | ' : '').'Abono cancelado por '.$user->name),
                'decidido_por' => $user->id, 'decidido_em' => now()]);
            return $s;
        }
        if ($s->status === 'aprovada' && $s->tipo === 'ajuste') {
            throw new DomainException('Ajuste aprovado já virou marcação; para retirá-lo, o DP desconsidera a marcação no espelho.');
        }
        throw new DomainException('Esta solicitação não pode ser cancelada.');
    }

    private function validarDecisao(Solicitacao $s, User $decisor): void
    {
        if ($s->status !== 'pendente') {
            throw new DomainException('Esta solicitação já foi '.mb_strtolower(Solicitacao::STATUS[$s->status] ?? $s->status).'.');
        }
        if (! $decisor->podeAprovarPara($s->colaborador)) {
            throw new DomainException('Você não pode decidir esta solicitação.');
        }
    }

    private function validarPeriodoAberto(Solicitacao $s): void
    {
        foreach (CarbonPeriod::create($s->data, $s->fim) as $dia) {
            if (PeriodoFechamento::fechado($s->colaborador->empresa_id, $dia->toDateString())) {
                throw new DomainException('A competência '.$dia->format('m/Y').' já está fechada; o DP precisa reabri-la antes.');
            }
        }
    }
}
