@extends('layouts.app')
@section('titulo', 'Espelho de ponto')
@section('conteudo')
@php($podeAjustar = auth()->user()->temPerfil('admin', 'dp') && ! $fechado)
<form class="filtros" method="get">
    @if($colaboradores->count() > 1)
        <div style="min-width:260px"><label>Colaborador</label><select name="colaborador_id" onchange="this.form.submit()">
            @foreach($colaboradores as $c)<option value="{{ $c->id }}" @selected($colaborador?->id === $c->id)>{{ $c->nome }} ({{ $c->matricula }})</option>@endforeach</select></div>
    @endif
    <div><label>Competência</label><input type="month" name="competencia" value="{{ $inicio->format('Y-m') }}" onchange="this.form.submit()"></div>
    @if($colaborador)
        <div><a class="btn sec" target="_blank" href="{{ route('espelho', ['colaborador_id' => $colaborador->id, 'competencia' => $inicio->format('Y-m'), 'imprimir' => 1]) }}">Imprimir</a></div>
    @endif
    @if($colaborador && $colaborador->user_id === auth()->id())
        <div><a class="btn sec" href="{{ route('solicitacoes.index') }}">Solicitar ajuste ou abono</a></div>
    @endif
    @if(auth()->user()->temPerfil('admin', 'rh', 'dp', 'gestor'))
        <div><a class="btn sec" href="{{ route('espelho.exportar', ['competencia' => $inicio->format('Y-m')]) }}">Exportar resumo (Excel)</a></div>
    @endif
</form>

@if(! $colaborador)
    <div class="card">Nenhum colaborador disponível.</div>
@else
    <div class="grade">
        <div class="kpi"><span>Colaborador</span><strong style="font-size:17px">{{ $colaborador->nome }}</strong><div class="muted">{{ $colaborador->cargo?->nome }} · {{ $colaborador->setor?->nome }}</div></div>
        <div class="kpi"><span>Saldo da competência</span><strong class="{{ $resultado['totais']['saldo'] < 0 ? 'neg' : 'pos' }}">{{ \App\Services\Apuracao::hhmm($resultado['totais']['saldo'], true) }}</strong></div>
        <div class="kpi"><span>Banco de horas</span><strong class="{{ $saldoBanco < 0 ? 'neg' : 'pos' }}">{{ \App\Services\Apuracao::hhmm($saldoBanco, true) }}</strong></div>
        <div class="kpi"><span>Situação</span><strong style="font-size:17px">@if($fechado)<span class="selo ok">Fechada</span>@else<span class="selo alerta">Aberta</span>@endif</strong>
            @if($fechado)<div class="muted">em {{ $fechado->fechado_em->format('d/m/Y') }}</div>@endif</div>
    </div>

    @if($podeAjustar)
        <details class="card"><summary>Incluir marcação esquecida</summary>
            <form method="post" action="{{ route('ajustes.incluir') }}" class="filtros" style="margin-top:12px">
                @csrf <input type="hidden" name="colaborador_id" value="{{ $colaborador->id }}">
                <div><label>Data</label><input type="date" name="data" min="{{ $inicio->format('Y-m-d') }}" max="{{ min($fim, today())->format('Y-m-d') }}" required></div>
                <div><label>Hora</label><input type="time" name="hora" required></div>
                <div style="flex:1"><label>Motivo (fica registrado)</label><input name="motivo" required minlength="5" placeholder="Ex.: esqueceu de registrar a saída, confirmado pelo gestor"></div>
                <div><button class="btn">Incluir</button></div>
            </form>
            <p class="muted">Para retirar uma marcação indevida, use o × ao lado dela. Nada é apagado: a marcação original continua guardada.</p>
        </details>
    @endif

    @include('ponto._tabela-espelho')
@endif
@endsection
