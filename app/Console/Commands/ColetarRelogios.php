<?php

namespace App\Console\Commands;

use App\Models\ImportacaoAfd;
use App\Models\Relogio;
use App\Services\Afd\ImportadorAfd;
use Illuminate\Console\Command;

/**
 * Coleta automática: importa todo AFD novo encontrado na "pasta de coleta" de
 * cada relógio ativo (pasta onde o pendrive, o software do fabricante ou uma
 * rotina de rede grava os arquivos). Arquivos já importados são reconhecidos
 * pelo hash e ignorados.
 */
class ColetarRelogios extends Command
{
    protected $signature = 'relogios:coletar {relogio? : ID do relógio (padrão: todos os ativos)}';

    protected $description = 'Importa os arquivos AFD novos das pastas de coleta dos relógios de ponto';

    public function handle(ImportadorAfd $importador): int
    {
        $relogios = Relogio::where('ativo', true)->whereNotNull('pasta_afd')
            ->when($this->argument('relogio'), fn ($q, $id) => $q->whereKey($id))->get();

        foreach ($relogios as $relogio) {
            $pasta = rtrim($relogio->pasta_afd, '\\/');
            if (! is_dir($pasta)) {
                $this->warn("[{$relogio->nome}] pasta não encontrada: {$pasta}");
                continue;
            }
            $arquivos = array_merge(glob($pasta.DIRECTORY_SEPARATOR.'*.txt') ?: [], glob($pasta.DIRECTORY_SEPARATOR.'*.TXT') ?: []);
            sort($arquivos);
            foreach (array_unique($arquivos) as $arquivo) {
                $conteudo = @file_get_contents($arquivo);
                if ($conteudo === false || $conteudo === '') {
                    continue;
                }
                $hash = hash('sha256', $conteudo);
                if (ImportacaoAfd::where('relogio_id', $relogio->id)->where('arquivo_hash', $hash)->exists()) {
                    continue;
                }
                try {
                    $imp = $importador->importar($relogio, $conteudo, basename($arquivo), 'pasta');
                    $this->info("[{$relogio->nome}] ".basename($arquivo).": {$imp->importadas} importadas, {$imp->duplicadas} já existiam, {$imp->pendentes} pendentes");
                } catch (\Throwable $e) {
                    $this->error("[{$relogio->nome}] ".basename($arquivo).': '.$e->getMessage());
                }
            }
        }

        return self::SUCCESS;
    }
}
