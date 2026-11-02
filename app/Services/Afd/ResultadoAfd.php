<?php

namespace App\Services\Afd;

class ResultadoAfd
{
    public ?array $cabecalho = null;
    public ?string $leiaute = null;

    /** @var array<int, array{nsr:int, data_hora:\Carbon\CarbonImmutable, cpf:?string, pis:?string, linha:int}> */
    public array $marcacoes = [];

    public array $empregados = [];

    /** Quantidade de registros por tipo (2 a 7). */
    public array $contagem = [];

    public array $erros = [];
}
