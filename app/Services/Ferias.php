<?php

namespace App\Services;

use App\Models\Colaborador;
use App\Models\Feriado;
use App\Models\FeriasPeriodo;
use App\Models\FeriasProgramacao;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Collection;

/**
 * Regras de férias (CLT, após a Lei 13.467/2017):
 * - Período aquisitivo de 12 meses a partir da admissão; concessivo nos 12 meses seguintes (art. 134).
 * - Dias de direito conforme faltas injustificadas no aquisitivo (art. 130).
 * - Até 3 períodos de gozo, um com no mínimo 14 dias e os demais com no mínimo 5 (art. 134 §1º).
 * - Não pode começar nos 2 dias que antecedem feriado ou DSR (art. 134 §3º).
 * - Abono pecuniário (venda) de até 1/3 dos dias de direito (art. 143).
 * - Aviso com 30 dias de antecedência (art. 135) e pagamento em dobro fora do concessivo (art. 137): avisos.
 */
class Ferias
{
    public const MIN_PERIODO = 5;
    public const MIN_PERIODO_PRINCIPAL = 14;
    public const MAX_PERIODOS = 3;

    /** Cria os períodos aquisitivos que faltam, da admissão até hoje (ou até o desligamento). */
    public function sincronizarPeriodos(Colaborador $colaborador): Collection
    {
        if (! $colaborador->data_admissao || $colaborador->tipo_contrato === 'pj' || $colaborador->tipo_contrato === 'estagio') {
            return $colaborador->feriasPeriodos()->with('programacoes')->get();
        }
        $existentes = $colaborador->feriasPeriodos()->pluck('inicio')->map(fn ($d) => Carbon::parse($d)->toDateString())->all();
        $limite = $colaborador->data_demissao && $colaborador->data_demissao->lt(today()) ? $colaborador->data_demissao : today();
        $inicio = $colaborador->data_admissao->copy()->startOfDay();
        while ($inicio->lte($limite)) {
            $fim = $inicio->copy()->addYear()->subDay();
            if (! in_array($inicio->toDateString(), $existentes, true)) {
                FeriasPeriodo::create([
                    'colaborador_id' => $colaborador->id, 'inicio' => $inicio, 'fim' => $fim,
                    'limite_concessivo' => $fim->copy()->addYear(),
                ]);
            }
            $inicio = $fim->copy()->addDay();
        }
        return $colaborador->feriasPeriodos()->with('programacoes')->get();
    }

    /** Tabela do art. 130 da CLT. */
    public static function diasPorFaltas(int $faltas): int
    {
        return match (true) {
            $faltas <= 5 => 30,
            $faltas <= 14 => 24,
            $faltas <= 23 => 18,
            $faltas <= 32 => 12,
            default => 0,
        };
    }

    public static function maxAbono(FeriasPeriodo $periodo): int
    {
        return intdiv($periodo->dias_direito, 3);
    }

    /**
     * Valida uma programação. Retorna ['erros' => [...], 'avisos' => [...]].
     * Erros impedem o lançamento; avisos são mostrados a quem solicita e a quem aprova.
     */
    public function validar(FeriasPeriodo $periodo, Carbon $inicio, int $dias, int $abono = 0, ?int $ignorarId = null): array
    {
        $periodo->load('programacoes');
        $periodo->loadMissing('colaborador.jornadas.jornada');
        $colaborador = $periodo->colaborador;
        $erros = [];
        $avisos = [];
        $inicio = $inicio->copy()->startOfDay();
        $fim = $inicio->copy()->addDays(max($dias, 1) - 1);
        $saldo = $periodo->saldo($ignorarId);
        $outras = $periodo->programacoesAtivas()->where('id', '!=', $ignorarId);

        if ($periodo->perdido || $periodo->dias_direito === 0) {
            $erros[] = 'Este período aquisitivo não dá direito a férias.';
        }
        if (! $periodo->completo() && $inicio->lte($periodo->fim)) {
            $erros[] = 'O período aquisitivo só termina em '.$periodo->fim->format('d/m/Y').'; as férias devem começar depois dessa data.';
        }
        if ($dias < self::MIN_PERIODO) {
            $erros[] = 'Cada período de férias deve ter no mínimo '.self::MIN_PERIODO.' dias.';
        }
        if ($abono < 0 || $abono > self::maxAbono($periodo)) {
            $erros[] = 'A venda (abono pecuniário) é limitada a 1/3 dos dias de direito: até '.self::maxAbono($periodo).' dias.';
        }
        if ($abono > 0 && $outras->where('dias_abono', '>', 0)->isNotEmpty()) {
            $erros[] = 'A venda de 1/3 já foi solicitada em outra programação deste período.';
        }
        if ($dias + $abono > $saldo) {
            $erros[] = "Saldo insuficiente: restam {$saldo} dias neste período aquisitivo.";
        }
        if ($outras->count() + 1 > self::MAX_PERIODOS) {
            $erros[] = 'As férias podem ser divididas em no máximo '.self::MAX_PERIODOS.' períodos.';
        }

        // Fracionamento: depois desta programação ainda precisa caber um período de 14 dias, e não pode sobrar resto < 5
        $restante = $saldo - $dias - $abono;
        $temPrincipal = $dias >= self::MIN_PERIODO_PRINCIPAL || $outras->contains(fn ($p) => $p->dias >= self::MIN_PERIODO_PRINCIPAL);
        if (! $erros && ! $temPrincipal && $restante < self::MIN_PERIODO_PRINCIPAL) {
            $erros[] = 'Um dos períodos precisa ter no mínimo '.self::MIN_PERIODO_PRINCIPAL." dias; com esta programação sobrariam só {$restante} dias.";
        }
        if (! $erros && $restante > 0 && $restante < self::MIN_PERIODO) {
            $erros[] = "Sobrariam {$restante} dias, abaixo do mínimo de ".self::MIN_PERIODO.' por período. Ajuste os dias ou a venda.';
        }
        if (! $erros && $restante > 0 && $outras->count() + 1 === self::MAX_PERIODOS) {
            $erros[] = "Este seria o 3º período e ainda sobrariam {$restante} dias sem poder ser programados.";
        }

        // Sobreposição com outras férias do próprio colaborador
        $conflito = FeriasProgramacao::where('colaborador_id', $colaborador->id)->ativas()->where('id', '!=', $ignorarId)
            ->whereDate('inicio', '<=', $fim)->whereDate('fim', '>=', $inicio)->first();
        if ($conflito) {
            $erros[] = 'Já existem férias de '.$conflito->inicio->format('d/m/Y').' a '.$conflito->fim->format('d/m/Y').' nesse intervalo.';
        }

        // Início nos 2 dias que antecedem feriado ou DSR (domingo)
        $bloqueio = $this->antecedeFeriadoOuDsr($colaborador, $inicio);
        if ($bloqueio) {
            $semanal = $colaborador->jornadaEm($inicio)?->jornada?->tipo !== 'revezamento';
            $msg = "Pela CLT (art. 134 §3º) as férias não podem começar nos 2 dias anteriores a {$bloqueio}.";
            if ($semanal) {
                $erros[] = $msg;
            } else {
                $avisos[] = $msg.' Confira a escala de revezamento.';
            }
        }

        if ($inicio->lt(today()->addDays(30))) {
            $avisos[] = 'Início com menos de 30 dias de antecedência: o aviso de férias deve ser dado com 30 dias (art. 135).';
        }
        if ($fim->gt($periodo->limite_concessivo)) {
            $avisos[] = 'Termina depois do fim do período concessivo ('.$periodo->limite_concessivo->format('d/m/Y').'): os dias excedentes são pagos em dobro (art. 137).';
        }
        if ($abono > 0 && today()->gt($periodo->fim->copy()->subDays(15))) {
            $avisos[] = 'A venda foi pedida a menos de 15 dias do fim do período aquisitivo; fica a critério da empresa (art. 143 §1º).';
        }
        foreach ($this->colegasNoPeriodo($colaborador, $inicio, $fim, $ignorarId) as $p) {
            $avisos[] = "{$p->colaborador->nome}, do mesmo setor, tem férias de {$p->inicio->format('d/m')} a {$p->fim->format('d/m')}.";
        }

        return ['erros' => $erros, 'avisos' => $avisos, 'fim' => $fim];
    }

    public function programar(FeriasPeriodo $periodo, array $dados, User $user, bool $aprovarDireto = false): FeriasProgramacao
    {
        $inicio = Carbon::parse($dados['inicio']);
        $dias = (int) $dados['dias'];
        $abono = (int) ($dados['dias_abono'] ?? 0);
        $r = $this->validar($periodo, $inicio, $dias, $abono);
        if ($r['erros']) {
            throw new DomainException(implode(' ', $r['erros']));
        }
        return FeriasProgramacao::create([
            'periodo_id' => $periodo->id, 'colaborador_id' => $periodo->colaborador_id,
            'inicio' => $inicio, 'fim' => $r['fim'], 'dias' => $dias, 'dias_abono' => $abono,
            'adiantamento_13' => (bool) ($dados['adiantamento_13'] ?? false), 'observacao' => $dados['observacao'] ?? null,
            'status' => $aprovarDireto ? 'aprovada' : 'pendente', 'criado_por' => $user->id,
            'decidido_por' => $aprovarDireto ? $user->id : null, 'decidido_em' => $aprovarDireto ? now() : null,
        ]);
    }

    public function aprovar(FeriasProgramacao $p, User $decisor, ?string $resposta = null): void
    {
        $this->validarDecisao($p, $decisor);
        // revalida (o saldo ou a escala podem ter mudado desde o pedido)
        $r = $this->validar($p->periodo, $p->inicio, $p->dias, $p->dias_abono, $p->id);
        if ($r['erros']) {
            throw new DomainException(implode(' ', $r['erros']));
        }
        $p->update(['status' => 'aprovada', 'resposta' => $resposta, 'decidido_por' => $decisor->id, 'decidido_em' => now()]);
    }

    public function rejeitar(FeriasProgramacao $p, User $decisor, string $resposta): void
    {
        $this->validarDecisao($p, $decisor);
        $p->update(['status' => 'rejeitada', 'resposta' => $resposta, 'decidido_por' => $decisor->id, 'decidido_em' => now()]);
    }

    public function cancelar(FeriasProgramacao $p, User $user): void
    {
        $proprio = $p->colaborador->user_id === $user->id;
        $permitido = ($p->status === 'pendente' && ($proprio || $user->podeAprovarPara($p->colaborador)))
            || ($p->status === 'aprovada' && $user->ehGestaoPessoal() && $p->inicio->gt(today()));
        if (! $permitido) {
            throw new DomainException($p->status === 'aprovada'
                ? 'Férias aprovadas só podem ser canceladas pelo RH/DP antes do início.'
                : 'Esta programação não pode ser cancelada.');
        }
        $p->update(['status' => 'cancelada', 'decidido_por' => $user->id, 'decidido_em' => now()]);
    }

    private function validarDecisao(FeriasProgramacao $p, User $decisor): void
    {
        if ($p->status !== 'pendente') {
            throw new DomainException('Esta programação já foi decidida.');
        }
        if (! $decisor->podeAprovarPara($p->colaborador)) {
            throw new DomainException('Você não pode decidir estas férias.');
        }
    }

    private function antecedeFeriadoOuDsr(Colaborador $colaborador, Carbon $inicio): ?string
    {
        foreach ([1, 2] as $n) {
            $d = $inicio->copy()->addDays($n);
            $feriado = Feriado::whereDate('data', $d)
                ->where(fn ($q) => $q->whereNull('empresa_id')->orWhere('empresa_id', $colaborador->empresa_id))->first();
            if ($feriado) {
                return 'feriado ('.$feriado->descricao.', '.$d->format('d/m').')';
            }
            if ($d->isSunday()) {
                return 'repouso semanal (domingo '.$d->format('d/m').')';
            }
        }
        return null;
    }

    /** Programações ativas de colegas do mesmo setor que se sobrepõem ao intervalo. */
    public function colegasNoPeriodo(Colaborador $colaborador, Carbon $inicio, Carbon $fim, ?int $ignorarId = null): Collection
    {
        if (! $colaborador->setor_id) {
            return collect();
        }
        return FeriasProgramacao::ativas()->with('colaborador')->where('id', '!=', $ignorarId)
            ->where('colaborador_id', '!=', $colaborador->id)
            ->whereHas('colaborador', fn ($q) => $q->where('setor_id', $colaborador->setor_id))
            ->whereDate('inicio', '<=', $fim)->whereDate('fim', '>=', $inicio)->get();
    }

    /**
     * Alertas de vencimento: períodos com dias ainda não programados cujo prazo para iniciar o gozo
     * (fim do concessivo menos o saldo) está próximo ou já passou.
     * Nível: vencido (< 0 dias), critico (até 60), atencao (até 120), aquisitivo_proximo (período completo sem programação).
     */
    public function alertas(Collection $colaboradores): Collection
    {
        $alertas = collect();
        foreach ($colaboradores as $c) {
            foreach ($this->sincronizarPeriodos($c) as $p) {
                $saldo = $p->saldo();
                if ($saldo <= 0 || ! $p->completo()) {
                    continue;
                }
                $ultimoInicio = $p->limite_concessivo->copy()->subDays($saldo - 1);
                $faltam = (int) today()->diffInDays($ultimoInicio, false);
                $nivel = match (true) {
                    $p->limite_concessivo->lt(today()) => 'vencido',
                    $faltam < 0 => 'vencido',
                    $faltam <= 60 => 'critico',
                    $faltam <= 120 => 'atencao',
                    default => null,
                };
                if ($nivel) {
                    $alertas->push((object) ['colaborador' => $c, 'periodo' => $p, 'saldo' => $saldo, 'ultimo_inicio' => $ultimoInicio,
                        'faltam' => $faltam, 'nivel' => $nivel]);
                }
            }
        }
        return $alertas->sortBy('faltam')->values();
    }

    public const NIVEIS = ['vencido' => ['Vencido (em dobro)', 'erro'], 'critico' => ['Crítico', 'erro'], 'atencao' => ['Atenção', 'alerta']];
}
