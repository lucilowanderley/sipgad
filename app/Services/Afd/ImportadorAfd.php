<?php

namespace App\Services\Afd;

use App\Models\Colaborador;
use App\Models\ImportacaoAfd;
use App\Models\Marcacao;
use App\Models\Relogio;
use App\Services\RegistroPonto;
use DomainException;
use Illuminate\Support\Facades\Storage;

/**
 * Importa as marcações de um AFD para o SIPGAD.
 *
 * - Cada marcação do relógio vira uma Marcacao com origem "relogio", ligada ao
 *   relógio e ao NSR original dele (o NSR do relógio nunca é importado duas vezes).
 * - O colaborador é localizado pelo CPF (Portaria 671) ou pelo PIS (Portaria 1510).
 * - Marcações sem colaborador cadastrado ou em competência fechada ficam como
 *   pendência; basta corrigir o cadastro e reprocessar o mesmo arquivo.
 * - O arquivo original é guardado em storage/app/private/afd.
 */
class ImportadorAfd
{
    public function __construct(private LeitorAfd $leitor, private RegistroPonto $registro) {}

    public function importar(Relogio $relogio, string $conteudo, string $nomeArquivo, string $origem = 'upload', ?int $userId = null, ?string $caminhoExistente = null): ImportacaoAfd
    {
        $afd = $this->leitor->ler($conteudo);
        $pendencias = [];

        $this->conferirCabecalho($relogio, $afd, $pendencias);

        $hash = hash('sha256', $conteudo);
        $caminho = $caminhoExistente ?? 'afd/'.$relogio->id.'/'.now()->format('Ymd_His').'_'.substr($hash, 0, 8).'.txt';
        if (! $caminhoExistente) {
            Storage::disk('local')->put($caminho, $conteudo);
        }

        $jaImportados = Marcacao::where('relogio_id', $relogio->id)->pluck('nsr_relogio')->flip();
        [$porCpf, $porPis] = $this->indices($relogio);

        $importadas = $duplicadas = 0;
        foreach ($afd->marcacoes as $m) {
            if (isset($jaImportados[$m['nsr']])) {
                $duplicadas++;
                continue;
            }
            $colaborador = $m['cpf'] ? ($porCpf[$m['cpf']] ?? null) : ($porPis[$m['pis']] ?? null);
            if (! $colaborador) {
                $pendencias[] = [
                    'nsr' => $m['nsr'], 'data_hora' => $m['data_hora']->format('d/m/Y H:i'),
                    'identificador' => $m['cpf'] ? 'CPF '.$this->mascararCpf($m['cpf']) : 'PIS '.$m['pis'],
                    'motivo' => 'Colaborador não encontrado no cadastro',
                ];
                continue;
            }
            try {
                $this->registro->registrar($colaborador, $m['data_hora'], [
                    'origem' => 'relogio', 'relogio_id' => $relogio->id, 'nsr_relogio' => $m['nsr'], 'criado_por' => $userId,
                ]);
                $importadas++;
                $jaImportados[$m['nsr']] = true;
            } catch (DomainException $e) {
                $pendencias[] = [
                    'nsr' => $m['nsr'], 'data_hora' => $m['data_hora']->format('d/m/Y H:i'),
                    'identificador' => $colaborador->nome, 'motivo' => $e->getMessage(),
                ];
            }
        }

        foreach ($afd->erros as $erro) {
            $pendencias[] = ['nsr' => null, 'data_hora' => null, 'identificador' => null, 'motivo' => 'Registro ilegível — '.$erro];
        }

        $nsrs = array_column($afd->marcacoes, 'nsr');
        if ($nsrs && max($nsrs) > $relogio->ultimo_nsr) {
            $relogio->forceFill(['ultimo_nsr' => max($nsrs)]);
        }
        $relogio->forceFill(['ultima_coleta_em' => now()])->saveQuietly();

        return ImportacaoAfd::create([
            'relogio_id' => $relogio->id,
            'user_id' => $userId,
            'origem' => $origem,
            'arquivo_nome' => mb_substr($nomeArquivo, 0, 255),
            'arquivo_path' => $caminho,
            'arquivo_hash' => $hash,
            'leiaute' => $afd->leiaute,
            'nsr_inicial' => $nsrs ? min($nsrs) : null,
            'nsr_final' => $nsrs ? max($nsrs) : null,
            'marcacoes_arquivo' => count($afd->marcacoes),
            'importadas' => $importadas,
            'duplicadas' => $duplicadas,
            'pendentes' => count(array_filter($pendencias, fn ($p) => $p['nsr'] !== null)),
            'pendencias' => $pendencias ?: null,
        ]);
    }

    /** Reimporta o arquivo guardado (após cadastrar colaboradores ou reabrir competência). */
    public function reprocessar(ImportacaoAfd $importacao, ?int $userId = null): ImportacaoAfd
    {
        $conteudo = Storage::disk('local')->get($importacao->arquivo_path);
        if ($conteudo === null) {
            throw new DomainException('O arquivo original desta importação não foi encontrado.');
        }
        return $this->importar($importacao->relogio, $conteudo, $importacao->arquivo_nome, 'reprocessamento', $userId, $importacao->arquivo_path);
    }

    private function conferirCabecalho(Relogio $relogio, ResultadoAfd $afd, array &$pendencias): void
    {
        $cab = $afd->cabecalho;
        if (! $cab) {
            $pendencias[] = ['nsr' => null, 'data_hora' => null, 'identificador' => null, 'motivo' => 'Arquivo sem cabeçalho (registro tipo 1).'];
            return;
        }

        $numeroArquivo = ltrim(preg_replace('/\D/', '', $cab['numero_rep']), '0');
        $numeroRelogio = ltrim(preg_replace('/\D/', '', (string) $relogio->numero_fabricacao), '0');
        if ($numeroRelogio !== '' && $numeroArquivo !== '' && $relogio->tipo_rep === 'C' && $numeroArquivo !== $numeroRelogio) {
            throw new DomainException("Este AFD é do relógio nº {$cab['numero_rep']}, e não do relógio \"{$relogio->nome}\" (nº {$relogio->numero_fabricacao}).");
        }
        if ($numeroRelogio === '' && $numeroArquivo !== '') {
            $relogio->forceFill(['numero_fabricacao' => $cab['numero_rep']]);
        }
        if (! $relogio->modelo && ! empty($cab['modelo'])) {
            $relogio->forceFill(['modelo' => $cab['modelo']]);
        }

        $cnpjEmpresa = preg_replace('/\D/', '', (string) $relogio->empresa?->cnpj);
        if ($cnpjEmpresa && $cab['empregador'] && ltrim($cab['empregador'], '0') !== ltrim($cnpjEmpresa, '0')) {
            $pendencias[] = ['nsr' => null, 'data_hora' => null, 'identificador' => null,
                'motivo' => "Aviso: o empregador do AFD ({$cab['empregador']}) é diferente do CNPJ da empresa do relógio ({$cnpjEmpresa})."];
        }
    }

    /** Índices CPF → colaborador e PIS → colaborador, priorizando a empresa do relógio. */
    private function indices(Relogio $relogio): array
    {
        $porCpf = $porPis = [];
        $colaboradores = Colaborador::orderByRaw('empresa_id = ? desc', [$relogio->empresa_id])->orderByDesc('ativo')->get();
        foreach ($colaboradores as $c) {
            $cpf = preg_replace('/\D/', '', (string) $c->cpf);
            $pis = ltrim(preg_replace('/\D/', '', (string) $c->pis), '0');
            if ($cpf !== '') {
                $porCpf[$cpf] ??= $c;
            }
            if ($pis !== '') {
                $porPis[$pis] ??= $c;
            }
        }
        return [$porCpf, $porPis];
    }

    private function mascararCpf(string $d): string
    {
        return substr($d, 0, 3).'.'.substr($d, 3, 3).'.'.substr($d, 6, 3).'-'.substr($d, 9, 2);
    }
}
