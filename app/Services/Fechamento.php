<?php

namespace App\Services;

use App\Models\BancoHorasLancamento;
use App\Models\Empresa;
use App\Models\PeriodoFechamento;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Fecha a competência (mês) de uma empresa: congela as marcações e,
 * se o banco de horas estiver ativo, lança o saldo do mês de cada colaborador.
 */
class Fechamento
{
    public function __construct(private Apuracao $apuracao) {}

    public function fechar(Empresa $empresa, string $competencia, ?int $userId = null): PeriodoFechamento
    {
        $inicio = Carbon::createFromFormat('Y-m-d', $competencia.'-01')->startOfDay();
        $fim = $inicio->copy()->endOfMonth()->startOfDay();

        if (PeriodoFechamento::where('empresa_id', $empresa->id)->where('competencia', $competencia)->exists()) {
            throw new DomainException("A competência {$inicio->format('m/Y')} já está fechada.");
        }
        if ($fim->gte(today())) {
            throw new DomainException('Só é possível fechar um mês que já terminou.');
        }

        return DB::transaction(function () use ($empresa, $competencia, $inicio, $fim, $userId) {
            $periodo = PeriodoFechamento::create([
                'empresa_id' => $empresa->id, 'competencia' => $competencia,
                'inicio' => $inicio, 'fim' => $fim, 'fechado_em' => now(), 'fechado_por' => $userId,
            ]);

            if ($empresa->banco_horas_ativo) {
                $colaboradores = $empresa->colaboradores()
                    ->where('data_admissao', '<=', $fim)
                    ->where(fn ($q) => $q->whereNull('data_demissao')->orWhere('data_demissao', '>=', $inicio))
                    ->get();
                foreach ($colaboradores as $c) {
                    $saldo = $this->apuracao->periodo($c, $inicio, $fim)['totais']['saldo'];
                    if ($saldo !== 0) {
                        BancoHorasLancamento::create([
                            'colaborador_id' => $c->id, 'periodo_id' => $periodo->id, 'data' => $fim,
                            'minutos' => $saldo, 'tipo' => 'fechamento',
                            'descricao' => 'Saldo da competência '.$inicio->format('m/Y'), 'criado_por' => $userId,
                        ]);
                    }
                }
            }
            return $periodo;
        });
    }

    public function reabrir(PeriodoFechamento $periodo): void
    {
        $posterior = PeriodoFechamento::where('empresa_id', $periodo->empresa_id)->where('inicio', '>', $periodo->inicio)->exists();
        if ($posterior) {
            throw new DomainException('Reabra primeiro as competências posteriores.');
        }
        DB::transaction(function () use ($periodo) {
            BancoHorasLancamento::where('periodo_id', $periodo->id)->get()->each->delete();
            $periodo->delete();
        });
    }
}
