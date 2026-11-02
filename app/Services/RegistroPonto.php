<?php

namespace App\Services;

use App\Models\Colaborador;
use App\Models\Empresa;
use App\Models\Marcacao;
use App\Models\PeriodoFechamento;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Grava marcações de ponto com NSR sequencial por empresa e hash encadeado,
 * de forma que qualquer alteração direta no banco quebre a cadeia.
 */
class RegistroPonto
{
    /** Intervalo mínimo entre duas batidas do mesmo colaborador (evita clique duplo). */
    public const INTERVALO_MINIMO_SEGUNDOS = 60;

    public function registrar(Colaborador $colaborador, CarbonInterface $dataHora, array $extra = []): Marcacao
    {
        if (! $colaborador->ativo) {
            throw new DomainException('Colaborador inativo não pode registrar ponto.');
        }
        if (PeriodoFechamento::fechado($colaborador->empresa_id, $dataHora->toDateString())) {
            throw new DomainException('O período de '.$dataHora->format('m/Y').' já está fechado.');
        }

        return DB::transaction(function () use ($colaborador, $dataHora, $extra) {
            $empresa = Empresa::whereKey($colaborador->empresa_id)->lockForUpdate()->firstOrFail();

            if (($extra['origem'] ?? 'web') === 'web') {
                $ultima = $colaborador->marcacoes()->validas()->latest('data_hora')->first();
                if ($ultima && abs($ultima->data_hora->diffInSeconds($dataHora)) < self::INTERVALO_MINIMO_SEGUNDOS) {
                    throw new DomainException('Você acabou de registrar o ponto às '.$ultima->data_hora->format('H:i:s').'.');
                }
            }

            $nsr = $empresa->ultimo_nsr + 1;
            $empresa->forceFill(['ultimo_nsr' => $nsr])->saveQuietly();

            $hashAnterior = Marcacao::where('empresa_id', $empresa->id)->orderByDesc('nsr')->value('hash') ?? '';
            $hash = hash('sha256', implode('|', [$hashAnterior, $nsr, $colaborador->id, $colaborador->cpf, $dataHora->format('Y-m-d H:i:s')]));

            return Marcacao::create(array_merge([
                'empresa_id' => $empresa->id,
                'colaborador_id' => $colaborador->id,
                'nsr' => $nsr,
                'data_hora' => $dataHora,
                'origem' => 'web',
                'hash' => $hash,
            ], $extra));
        });
    }

    /** Confere a cadeia de hashes da empresa. Retorna o NSR do primeiro registro adulterado, ou null. */
    public function verificarCadeia(int $empresaId): ?int
    {
        $anterior = '';
        foreach (Marcacao::where('empresa_id', $empresaId)->with('colaborador')->orderBy('nsr')->cursor() as $m) {
            $esperado = hash('sha256', implode('|', [$anterior, $m->nsr, $m->colaborador_id, $m->colaborador->cpf, $m->data_hora->format('Y-m-d H:i:s')]));
            if (! hash_equals($esperado, $m->hash)) {
                return $m->nsr;
            }
            $anterior = $m->hash;
        }
        return null;
    }
}
