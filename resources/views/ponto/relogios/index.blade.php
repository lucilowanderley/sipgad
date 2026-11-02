@extends('layouts.app')
@section('titulo', 'Relógios de ponto')
@section('conteudo')
<p><a class="btn" href="{{ route('relogios.create') }}">Novo relógio</a></p>
<div class="tabela"><table>
    <tr><th>Nome</th><th>Equipamento</th><th>Nº de fabricação</th><th>Empresa</th><th>Último NSR</th><th>Última coleta</th><th class="num">Marcações</th><th></th></tr>
    @forelse($relogios as $r)
        <tr>
            <td><a href="{{ route('relogios.show', $r) }}">{{ $r->nome }}</a> @unless($r->ativo)<span class="selo">inativo</span>@endunless</td>
            <td>{{ $r->fabricante }} {{ $r->modelo }} <span class="muted">REP-{{ $r->tipo_rep }}</span></td>
            <td>{{ $r->numero_fabricacao ?: '—' }}</td>
            <td>{{ $r->empresa?->nome }}</td>
            <td>{{ $r->ultimo_nsr }}</td>
            <td>{{ $r->ultima_coleta_em?->format('d/m/Y H:i') ?? 'nunca' }}</td>
            <td class="num">{{ $r->marcacoes_count }}</td>
            <td class="num"><a class="btn sec peq" href="{{ route('relogios.show', $r) }}">Coletar</a></td>
        </tr>
    @empty
        <tr><td colspan="8" class="muted">Nenhum relógio cadastrado. Cadastre o relógio para importar as marcações dele (arquivo AFD).</td></tr>
    @endforelse
</table></div>
@endsection
