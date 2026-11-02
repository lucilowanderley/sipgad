@extends('layouts.app')
@section('titulo', 'Cronograma de férias')
@section('conteudo')
@php($pos = fn ($data) => max(0, min(100, ($data->dayOfYear - 1 + ($data->year - $ano) * $diasAno) / $diasAno * 100)))
<form class="filtros" method="get">
    <div><label>Setor</label><select name="setor_id" onchange="this.form.submit()">
        @foreach($setores as $s)<option value="{{ $s->id }}" @selected($setorId == $s->id)>{{ $s->nome }}{{ $s->relationLoaded('empresa') && $s->empresa ? ' · '.$s->empresa->nome : '' }}</option>@endforeach</select></div>
    <div><label>Ano</label><select name="ano" onchange="this.form.submit()">
        @foreach(range(today()->year - 2, today()->year + 2) as $a)<option @selected($a === $ano)>{{ $a }}</option>@endforeach</select></div>
</form>

<div class="legenda">
    <span><i style="background:var(--primaria)"></i>Aprovada</span>
    <span><i style="background:#f59e0b"></i>Aguardando aprovação</span>
    <span><i style="background:var(--perigo);width:3px"></i>Fim do concessivo (prazo de vencimento)</span>
    <span><i style="background:#2563eb;width:3px"></i>Hoje</span>
    <span><i style="background:var(--perigo);height:4px"></i>Mais de uma pessoa do setor fora</span>
</div>

@if($colaboradores->isEmpty())
    <div class="card">Nenhum colaborador ativo neste setor.</div>
@else
<div class="tabela"><div class="cronograma">
    <div class="linha cab"><div class="nome"></div><div class="trilha">
        @foreach(['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'] as $m)<span>{{ $m }}</span>@endforeach
    </div></div>
    @if($conflitos->isNotEmpty())
        <div class="linha"><div class="nome muted">Sobreposições</div><div class="trilha">
            @foreach($conflitos as $d)<div class="conflito" style="left:{{ ($d - 1) / $diasAno * 100 }}%;width:{{ 100 / $diasAno }}%"></div>@endforeach
        </div></div>
    @endif
    @foreach($colaboradores as $c)
        <div class="linha">
            <div class="nome" title="{{ $c->nome }}"><a href="{{ route('ferias.index', ['colaborador_id' => $c->id]) }}">{{ $c->nome }}</a></div>
            <div class="trilha">
                @if($ano === today()->year)<div class="hoje" style="left:{{ $pos(today()) }}%"></div>@endif
                @foreach($c->feriasPeriodos as $per)
                    @if($per->limite_concessivo->year === $ano && $per->saldo() > 0)
                        <div class="limite" style="left:{{ $pos($per->limite_concessivo) }}%" title="Concessivo do aquisitivo {{ $per->rotulo }} termina em {{ $per->limite_concessivo->format('d/m/Y') }} ({{ $per->saldo() }} dias sem programar)"></div>
                    @endif
                @endforeach
                @foreach($c->feriasProgramacoes as $f)
                    @php($ini = $pos($f->inicio->copy()->max($inicioAno)))
                    @php($fim = $pos($f->fim->copy()->min($fimAno)) + 100 / $diasAno)
                    <div @class(['barra', 'pendente' => $f->status === 'pendente']) style="left:{{ $ini }}%;width:{{ max($fim - $ini, 0.3) }}%"
                         title="{{ $f->inicio->format('d/m/Y') }} a {{ $f->fim->format('d/m/Y') }} · {{ $f->dias }} dias · {{ $f->situacao }}">{{ $f->inicio->format('d/m') }}</div>
                @endforeach
            </div>
        </div>
    @endforeach
</div></div>
@if($conflitos->isNotEmpty())
    <p class="muted">{{ $conflitos->count() }} dia(s) no ano com mais de uma pessoa do setor de férias.</p>
@endif
@endif
@endsection
