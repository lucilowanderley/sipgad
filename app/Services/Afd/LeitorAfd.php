<?php

namespace App\Services\Afd;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DomainException;

/**
 * Lê o Arquivo Fonte de Dados (AFD) gerado pelos relógios de ponto.
 *
 * Aceita os dois leiautes em uso no Brasil:
 *  - Portaria MTP 671/2021 (leiaute 004): marcação tipo 3 (REP-C/REP-A) com CPF e
 *    data/hora "AAAA-MM-DDThh:mm:00-0300", e tipo 7 (REP-P) com hash encadeado;
 *  - Portaria MTE 1510/2009: marcação tipo 3 com data DDMMAAAA, hora HHMM e PIS.
 *
 * O AFD dos relógios Dimep (SMART 671, Printpoint etc.) é obtido pela porta USB
 * fiscal, pelo comando F1+80+E (pendrive) ou pelo software do fabricante.
 */
class LeitorAfd
{
    public function ler(string $conteudo): ResultadoAfd
    {
        $resultado = new ResultadoAfd;

        if (! mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'ISO-8859-1');
        }
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);

        foreach (preg_split('/\r\n|\r|\n/', $conteudo) as $n => $linha) {
            $linha = rtrim($linha, "\x00\x1A");
            if (trim($linha) === '') {
                continue;
            }
            try {
                $this->lerLinha($linha, $n + 1, $resultado);
            } catch (\Throwable $e) {
                $resultado->erros[] = 'Linha '.($n + 1).': '.$e->getMessage();
            }
        }

        if (! $resultado->cabecalho && ! $resultado->marcacoes) {
            throw new DomainException('O arquivo não parece ser um AFD válido (sem cabeçalho e sem marcações).');
        }

        usort($resultado->marcacoes, fn ($a, $b) => $a['nsr'] <=> $b['nsr']);

        return $resultado;
    }

    private function lerLinha(string $l, int $n, ResultadoAfd $r): void
    {
        $nsr = substr($l, 0, 9);
        $tipo = substr($l, 9, 1);

        if (! ctype_digit($nsr)) {
            return; // linha de assinatura digital ou lixo de fim de arquivo
        }

        if ($nsr === '000000000' && $tipo === '1') {
            $r->cabecalho = $this->cabecalho($l);
            $r->leiaute = $r->cabecalho['leiaute'];
            return;
        }
        if ($nsr === '999999999') {
            return; // trailer
        }

        $r->contagem[$tipo] = ($r->contagem[$tipo] ?? 0) + 1;

        if ($tipo === '3') {
            if ($this->ehDataHora671(substr($l, 10, 24))) {
                $r->leiaute ??= '671';
                $r->marcacoes[] = [
                    'nsr' => (int) $nsr,
                    'data_hora' => $this->dataHora671(substr($l, 10, 24)),
                    'cpf' => $this->cpf(substr($l, 34, 12)),
                    'pis' => null,
                    'linha' => $n,
                ];
            } else {
                $r->leiaute ??= '1510';
                $r->marcacoes[] = [
                    'nsr' => (int) $nsr,
                    'data_hora' => $this->dataHora1510(substr($l, 10, 8), substr($l, 18, 4)),
                    'cpf' => null,
                    'pis' => ltrim(substr($l, 22, 12), '0') ?: null,
                    'linha' => $n,
                ];
            }
        } elseif ($tipo === '7') {
            $r->leiaute ??= '671';
            $r->marcacoes[] = [
                'nsr' => (int) $nsr,
                'data_hora' => $this->dataHora671(substr($l, 10, 24)),
                'cpf' => $this->cpf(substr($l, 34, 12)),
                'pis' => null,
                'linha' => $n,
            ];
        } elseif ($tipo === '5') {
            // Inclusão/alteração/exclusão de empregado no relógio: guardado só para conferência.
            if ($this->ehDataHora671(substr($l, 10, 24))) {
                $r->empregados[] = ['operacao' => substr($l, 34, 1), 'cpf' => $this->cpf(substr($l, 35, 12)), 'nome' => trim(substr($l, 47, 52))];
            } else {
                $r->empregados[] = ['operacao' => substr($l, 22, 1), 'pis' => ltrim(substr($l, 23, 12), '0'), 'nome' => trim(substr($l, 35, 52))];
            }
        }
    }

    private function cabecalho(string $l): array
    {
        $digitos = fn (string $s) => preg_replace('/\D/', '', $s);

        if (strlen($l) >= 290 && $this->ehDataHora671(substr($l, 226, 24))) {
            return [
                'leiaute' => '671',
                'empregador' => $digitos(substr($l, 11, 14)),
                'razao_social' => trim(substr($l, 39, 150)),
                'numero_rep' => trim(substr($l, 189, 17)),
                'data_inicial' => trim(substr($l, 206, 10)),
                'data_final' => trim(substr($l, 216, 10)),
                'gerado_em' => trim(substr($l, 226, 24)),
                'versao' => trim(substr($l, 250, 3)),
                'fabricante' => $digitos(substr($l, 254, 14)),
                'modelo' => trim(substr($l, 268, 30)),
            ];
        }

        return [
            'leiaute' => '1510',
            'empregador' => $digitos(substr($l, 11, 14)),
            'razao_social' => trim(substr($l, 37, 150)),
            'numero_rep' => trim(substr($l, 187, 17)),
            'data_inicial' => trim(substr($l, 204, 8)),
            'data_final' => trim(substr($l, 212, 8)),
            'gerado_em' => trim(substr($l, 220, 12)),
            'versao' => null,
            'fabricante' => null,
            'modelo' => null,
        ];
    }

    private function ehDataHora671(string $s): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?[+-]\d{2}:?\d{2}/', $s);
    }

    /** Converte "2026-11-02T12:01:00-0300" para o fuso do sistema. */
    private function dataHora671(string $s): CarbonImmutable
    {
        if (! preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})(:\d{2})?([+-]\d{2}):?(\d{2})/', $s, $m)) {
            throw new DomainException("data/hora inválida \"$s\"");
        }
        $dh = CarbonImmutable::createFromFormat('Y-m-d H:i:s P', "$m[1] $m[2]".($m[3] ?: ':00')." $m[4]:$m[5]");

        return $dh->setTimezone(config('app.timezone'));
    }

    private function dataHora1510(string $data, string $hora): CarbonImmutable
    {
        if (! ctype_digit($data.$hora)) {
            throw new DomainException("data/hora inválida \"$data $hora\"");
        }
        return CarbonImmutable::createFromFormat('dmY Hi', "$data $hora", config('app.timezone'))->startOfMinute();
    }

    private function cpf(string $s): ?string
    {
        $d = preg_replace('/\D/', '', $s);
        return $d === '' ? null : str_pad(substr($d, -11), 11, '0', STR_PAD_LEFT);
    }
}
