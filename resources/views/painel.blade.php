@extends('layouts.app')
@section('titulo', 'Painel')
@section('conteudo')
@php($h = fn ($m, $s = false) => \App\Services\Apuracao::hhmm($m, $s))

@if($colaborador)
    <div class="grade">
        <div class="kpi"><span>Batidas hoje</span><strong>{{ $hoje->count() }}</strong>
            <div class="muted">{{ $hoje->map(fn ($m) => $m->data_hora->format('H:i'))->implode(' · ') ?: 'Nenhuma ainda' }}</div></div>
        <div class="kpi"><span>Trabalhado no mês</span><strong>{{ $h($mes['trabalhado']) }}</strong><div class="muted">previsto até hoje {{ $h($mes['previsto']) }}</div></div>
        <div class="kpi"><span>Saldo do mês</span><strong class="{{ $mes['saldo'] < 0 ? 'neg' : 'pos' }}">{{ $h($mes['saldo'], true) }}</strong><div class="muted">ainda não fechado</div></div>
        <div class="kpi"><span>Banco de horas</span><strong class="{{ $saldoBanco < 0 ? 'neg' : 'pos' }}">{{ $h($saldoBanco, true) }}</strong><div class="muted">até o último fechamento</div></div>
    </div>
    <p style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn" href="{{ route('ponto.bater') }}">Registrar ponto</a>
        <a class="btn sec" href="{{ route('solicitacoes.index') }}">Nova solicitação</a>
        <a class="btn sec" href="{{ route('ferias.index') }}">Minhas férias</a>
        <a class="btn sec" href="{{ route('documentos.index') }}">Meus documentos</a>
    </p>

    <div class="grade">
        <div class="kpi"><span>Minhas solicitações</span><strong>{{ $minhasPendentes }}</strong><div class="muted">aguardando aprovação</div></div>
        <div class="kpi"><span>Próximas férias</span>
            @if($proximasFerias)<strong style="font-size:18px">{{ $proximasFerias->inicio->format('d/m/Y') }}</strong><div class="muted">{{ $proximasFerias->dias }} dias · {{ $proximasFerias->situacao }}</div>
            @else<strong style="font-size:18px">—</strong><div class="muted">nenhuma programada</div>@endif</div>
        <div class="kpi"><span>Férias a vencer</span>
            @if($alertaFerias)<strong style="font-size:18px" class="neg">{{ $alertaFerias->saldo }} dias</strong><div class="muted">iniciar até {{ $alertaFerias->ultimo_inicio->format('d/m/Y') }}</div>
            @else<strong style="font-size:18px" class="pos">Em dia</strong>@endif</div>
        <div class="kpi"><span>Meus documentos</span>
            @if($meusDocsAlerta->isNotEmpty() || $meusDocsFaltando->isNotEmpty())
                <strong style="font-size:18px" class="neg">{{ $meusDocsAlerta->count() + $meusDocsFaltando->count() }} pendência(s)</strong>
                <div class="muted">{{ $meusDocsAlerta->toBase()->map(fn ($d) => $d->tipo->nome.' '.($d->situacao() === 'vencido' ? 'vencido' : 'vence '.$d->validade->format('d/m')))->merge($meusDocsFaltando->map(fn ($n) => $n.' faltando'))->take(3)->implode(' · ') }}</div>
            @else<strong style="font-size:18px" class="pos">Em dia</strong>@endif</div>
    </div>
@endif

@if($comunicados->isNotEmpty())
    <h2 style="font-size:16px;margin-top:8px">Mural <a class="muted" style="font-weight:400" href="{{ route('mural.index') }}">ver todos</a></h2>
    <div class="lista-cards" style="margin-bottom:18px">
        @foreach($comunicados as $c)@include('mural._item')@endforeach
    </div>
@endif

@isset($aprovacoesPendentes)
    <div class="grade">
        <div class="kpi"><span>Aprovações pendentes</span><strong class="{{ $aprovacoesPendentes ? 'neg' : '' }}">{{ $aprovacoesPendentes }}</strong><div class="muted"><a href="{{ route('aprovacoes.index') }}">abrir caixa de aprovação</a></div></div>
        <div class="kpi"><span>Férias vencidas/críticas</span><strong class="{{ $feriasCriticas ? 'neg' : '' }}">{{ $feriasCriticas }}</strong><div class="muted"><a href="{{ route('ferias.controle') }}">controle de férias</a></div></div>
        <div class="kpi"><span>De férias hoje</span><strong>{{ $emFerias }}</strong></div>
        @isset($docsVencidos)
            <div class="kpi"><span>Documentos vencidos</span><strong class="{{ $docsVencidos ? 'neg' : '' }}">{{ $docsVencidos }}</strong><div class="muted"><a href="{{ route('documentos.vencimentos') }}">{{ $docsAConferir }} a conferir</a></div></div>
        @endisset
    </div>
@endisset

@isset($indicadores)
    <h2 style="font-size:16px;margin-top:24px">{{ auth()->user()->ehGestaoPessoal() ? 'Empresa' : 'Minha equipe' }} hoje</h2>
    <div class="grade">
        <div class="kpi"><span>Colaboradores ativos</span><strong>{{ $indicadores['ativos'] }}</strong></div>
        <div class="kpi"><span>Já registraram ponto</span><strong>{{ $indicadores['presentes'] }}</strong></div>
        <div class="kpi"><span>Sem registro hoje</span><strong>{{ $indicadores['ativos'] - $indicadores['presentes'] }}</strong></div>
        <div class="kpi"><span>Batidas hoje</span><strong>{{ $indicadores['marcacoes_hoje'] }}</strong></div>
    </div>
    @if($semPonto->isNotEmpty())
        <div class="card">
            <h2>Sem registro de ponto hoje</h2>
            <div class="tabela"><table>
                <tr><th>Colaborador</th><th>Setor</th><th></th></tr>
                @foreach($semPonto as $c)
                    <tr><td>{{ $c->nome }}</td><td>{{ $c->setor?->nome }}</td>
                        <td class="num"><a href="{{ route('espelho', ['colaborador_id' => $c->id]) }}">Espelho</a></td></tr>
                @endforeach
            </table></div>
            <p class="muted">Inclui quem está de folga hoje.</p>
        </div>
    @endif
@endisset

@if(! $colaborador && ! isset($indicadores))
    <div class="card">Seu usuário ainda não está vinculado a um colaborador. Peça ao RH para concluir seu cadastro.</div>
@endif
@endsection
