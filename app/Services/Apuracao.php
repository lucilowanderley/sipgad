<?php

namespace App\Services;

use App\Models\Colaborador;
use App\Models\Feriado;
use App\Models\FeriasProgramacao;
use App\Models\Solicitacao;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Calcula, dia a dia, horas previstas, trabalhadas, extras, débitos e adicional noturno.
 *
 * Regras adotadas (ajustáveis):
 * - Cada dia "possui" as batidas de uma janela que começa à 00:00, ou mais tarde quando a
 *   jornada do dia anterior atravessa a meia-noite (turno noturno).
 * - Batidas são pareadas em ordem (entrada, saída, entrada, saída...). Número ímpar = dia incompleto.
 * - Diferença de até `tolerancia_diaria_minutos` (padrão 10, CLT art. 58 §1º) não é computada.
 * - Feriado ou folga: previsto = 0, todo o tempo trabalhado vira extra.
 * - Noturno: minutos trabalhados entre 22h e 5h (hora real; a hora reduzida é calculada à parte).
 * - Férias aprovadas: previsto = 0 e o dia aparece como "Férias".
 * - Abono/atestado aprovado: o dia inteiro (ou os minutos informados) conta como trabalhado
 *   para fins de saldo, sem gerar falta. O tempo abonado aparece em coluna própria.
 */
class Apuracao
{
    private const MARGEM_POS_JORNADA_HORAS = 4;

    /** @return array{dias: Collection, totais: array} */
    public function periodo(Colaborador $colaborador, CarbonInterface $inicio, CarbonInterface $fim): array
    {
        $colaborador->loadMissing('jornadas.jornada.dias', 'empresa');
        $inicio = Carbon::parse($inicio)->startOfDay();
        $fim = Carbon::parse($fim)->startOfDay();

        $feriados = Feriado::whereDate('data', '>=', $inicio)->whereDate('data', '<=', $fim)
            ->where(fn ($q) => $q->whereNull('empresa_id')->orWhere('empresa_id', $colaborador->empresa_id))
            ->get()->keyBy(fn ($f) => $f->data->toDateString());

        $marcacoes = $colaborador->marcacoes()->validas()
            ->whereBetween('data_hora', [$inicio->copy()->subDay(), $fim->copy()->addDays(2)])
            ->orderBy('data_hora')->get();

        $tolerancia = (int) ($colaborador->empresa->tolerancia_diaria_minutos ?? 10);
        [$ini, $fi] = [$inicio->toDateString(), $fim->toDateString()];
        $abonos = Solicitacao::where('colaborador_id', $colaborador->id)->abonosAprovados($ini, $fi)->get();
        $ferias = FeriasProgramacao::where('colaborador_id', $colaborador->id)->where('status', 'aprovada')
            ->whereDate('inicio', '<=', $fi)->whereDate('fim', '>=', $ini)->get();
        $dias = collect();

        foreach (CarbonPeriod::create($inicio, $fim) as $data) {
            $d = $data->toDateString();
            $dias->push($this->dia($colaborador, $data, $marcacoes, $feriados, $tolerancia,
                $abonos->filter(fn ($a) => $a->cobre($d)),
                $ferias->first(fn ($f) => $f->inicio->toDateString() <= $d && $f->fim->toDateString() >= $d)));
        }

        $soma = fn ($k) => $dias->sum($k);
        return [
            'dias' => $dias,
            'totais' => [
                'previsto' => $soma('previsto'),
                'trabalhado' => $soma('trabalhado'),
                'extras' => $soma('extras'),
                'debito' => $soma('debito'),
                'saldo' => $soma('saldo'),
                'noturno' => $soma('noturno'),
                'abonado' => $soma('abonado'),
                'dias_ferias' => $dias->where('ferias', true)->count(),
                'faltas' => $dias->where('falta', true)->count(),
                'incompletos' => $dias->where('incompleto', true)->count(),
            ],
        ];
    }

    public function dia(Colaborador $colaborador, CarbonInterface $data, Collection $marcacoes, Collection $feriados, int $tolerancia,
        ?Collection $abonos = null, ?FeriasProgramacao $ferias = null): array
    {
        $data = Carbon::parse($data)->startOfDay();
        $abonos ??= collect();
        $base = ['data' => $data, 'previsto' => 0, 'trabalhado' => 0, 'extras' => 0, 'debito' => 0, 'saldo' => 0,
            'noturno' => 0, 'abonado' => 0, 'falta' => false, 'incompleto' => false, 'feriado' => null, 'ocorrencia' => null,
            'ferias' => false, 'marcacoes' => collect(), 'previstos' => []];

        $admitido = $colaborador->data_admissao && $data->gte($colaborador->data_admissao);
        $desligado = $colaborador->data_demissao && $data->gt($colaborador->data_demissao);
        if (! $admitido || $desligado) {
            return array_merge($base, ['ocorrencia' => $desligado ? 'Desligado' : 'Antes da admissão', 'fora_contrato' => true]);
        }

        $previstos = $this->previstos($colaborador, $data);
        $feriado = $feriados->get($data->toDateString());
        if ($feriado || $ferias) {
            $previstos = [];
        }
        if ($data->isAfter(today())) {
            // Dia futuro: mostra o previsto, mas não gera falta nem débito
            return array_merge($base, ['previstos' => $previstos, 'feriado' => $feriado?->descricao, 'ferias' => (bool) $ferias,
                'ocorrencia' => $ferias ? 'Férias' : ($feriado ? 'Feriado: '.$feriado->descricao : null), 'futuro' => true]);
        }

        [$janelaIni, $janelaFim] = [$this->inicioJanela($colaborador, $data), $this->inicioJanela($colaborador, $data->copy()->addDay())];
        $doDia = $marcacoes->filter(fn ($m) => $m->data_hora->gte($janelaIni) && $m->data_hora->lt($janelaFim))->values();

        $previsto = array_sum(array_map(fn ($p) => (int) $p[0]->diffInMinutes($p[1]), $previstos));
        $trabalhado = 0;
        $noturno = 0;
        for ($i = 0; $i + 1 < $doDia->count(); $i += 2) {
            $ent = $doDia[$i]->data_hora->copy()->startOfMinute();
            $sai = $doDia[$i + 1]->data_hora->copy()->startOfMinute();
            $trabalhado += (int) $ent->diffInMinutes($sai);
            $noturno += $this->minutosNoturnos($ent, $sai);
        }

        // Dia em andamento: só apura quando a jornada prevista já terminou (+ margem)
        $emAndamento = $previstos && now()->lt(end($previstos)[1]->copy()->addHours(self::MARGEM_POS_JORNADA_HORAS));
        if ($emAndamento) {
            return array_merge($base, ['previstos' => $previstos, 'marcacoes' => $doDia, 'trabalhado' => $trabalhado,
                'noturno' => $noturno, 'ocorrencia' => 'Em andamento', 'futuro' => true]);
        }

        // Abono: dia inteiro cobre todo o previsto; parcial cobre até o que faltou para fechar a jornada
        $abonado = 0;
        $abonoDiaInteiro = $abonos->contains(fn ($a) => ! $a->minutos);
        if ($previsto > 0 && $abonos->isNotEmpty()) {
            $faltante = max($previsto - $trabalhado, 0);
            $abonado = $abonoDiaInteiro ? $faltante : min((int) $abonos->sum('minutos'), $faltante);
        }

        $saldo = $trabalhado + $abonado - $previsto;
        if ($previsto > 0 && abs($saldo) <= $tolerancia) {
            $saldo = 0;
        }

        $incompleto = $doDia->count() % 2 === 1;
        $falta = $previsto > 0 && $doDia->isEmpty() && ! $abonoDiaInteiro;
        $tipoAbono = $abonos->contains('tipo', 'atestado') ? 'Atestado' : 'Abono';
        $ocorrencia = match (true) {
            (bool) $ferias => 'Férias',
            (bool) $feriado => 'Feriado: '.$feriado->descricao,
            $abonado > 0 || ($abonos->isNotEmpty() && $previsto > 0) => $tipoAbono.($abonoDiaInteiro ? '' : ' parcial'),
            $falta => 'Falta',
            $incompleto => 'Marcação ímpar',
            $previsto === 0 && $doDia->isEmpty() => 'Folga',
            default => null,
        };

        return array_merge($base, [
            'previsto' => $previsto,
            'trabalhado' => $trabalhado,
            'saldo' => $saldo,
            'extras' => max($saldo, 0),
            'debito' => max(-$saldo, 0),
            'noturno' => $noturno,
            'abonado' => $abonado,
            'ferias' => (bool) $ferias,
            'falta' => $falta,
            'incompleto' => $incompleto,
            'feriado' => $feriado?->descricao,
            'ocorrencia' => $ocorrencia,
            'marcacoes' => $doDia,
            'previstos' => $previstos,
        ]);
    }

    private function previstos(Colaborador $colaborador, CarbonInterface $data): array
    {
        $vinculo = $colaborador->jornadaEm($data);
        return $vinculo ? $vinculo->jornada->intervalosPrevistos($data, $vinculo->data_base_revezamento ?? $vinculo->inicio) : [];
    }

    /** Início da janela de batidas do dia: 00:00, empurrado se o turno da véspera passa da meia-noite. */
    private function inicioJanela(Colaborador $colaborador, Carbon $data): Carbon
    {
        $inicio = $data->copy()->startOfDay();
        $vespera = $this->previstos($colaborador, $data->copy()->subDay());
        if ($vespera) {
            $fimVespera = end($vespera)[1];
            if ($fimVespera->gt($inicio)) {
                $corte = $fimVespera->copy()->addHours(self::MARGEM_POS_JORNADA_HORAS);
                $hoje = $this->previstos($colaborador, $data);
                if ($hoje && $hoje[0][0]->copy()->subMinutes(30)->lt($corte)) {
                    $corte = $hoje[0][0]->copy()->subMinutes(30);
                }
                return $corte->max($inicio);
            }
        }
        return $inicio;
    }

    public function minutosNoturnos(Carbon $ini, Carbon $fim): int
    {
        $total = 0;
        $dia = $ini->copy()->subDay()->startOfDay();
        while ($dia->lt($fim)) {
            $nIni = $dia->copy()->setTime(22, 0);
            $nFim = $dia->copy()->addDay()->setTime(5, 0);
            $a = $ini->max($nIni);
            $b = $fim->min($nFim);
            if ($b->gt($a)) {
                $total += (int) $a->diffInMinutes($b);
            }
            $dia->addDay();
        }
        return $total;
    }

    /** 52min30s = 1 hora noturna reduzida (CLT art. 73 §1º). */
    public static function horaNoturnaReduzida(int $minutos): int
    {
        return (int) round($minutos * 60 / 52.5);
    }

    public static function hhmm(int $minutos, bool $sinal = false): string
    {
        $neg = $minutos < 0;
        $m = abs($minutos);
        $txt = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        return ($neg ? '-' : ($sinal && $minutos > 0 ? '+' : '')).$txt;
    }
}
