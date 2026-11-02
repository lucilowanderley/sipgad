@extends('layouts.app')
@section('titulo', 'Controle de férias')
@section('conteudo')
<form class="filtros" method="get">
    <div><label>Setor</label><select name="setor_id" onchange="this.form.submit()"><option value="">Todos</option>
        @foreach($setores as $s)<option value="{{ $s->id }}" @selected(request('setor_id') == $s->id)>{{ $s->nome }}</option>@endforeach</select></div>
    <div><a class="btn sec" href="{{ route('ferias.cronograma', ['setor_id' => request('setor_id')]) }}">Cronograma</a></div>
</form>

<div class="grade">
    <div class="kpi"><span>Vencidas (em dobro)</span><strong class="neg">{{ $alertas->where('nivel', 'vencido')->count() }}</strong></div>
    <div class="kpi"><span>Críticas (até 60 dias)</span><strong class="neg">{{ $alertas->where('nivel', 'critico')->count() }}</strong></div>
    <div class="kpi"><span>Atenção (até 120 dias)</span><strong>{{ $alertas->where('nivel', 'atencao')->count() }}</strong></div>
    <div class="kpi"><span>Saindo nos próximos 90 dias</span><strong>{{ $proximas->count() }}</strong></div>
</div>

<div class="card">
    <h2>Alertas de vencimento</h2>
    <p class="muted">Períodos aquisitivos completos com dias ainda não programados. "Iniciar até" é a última data para começar o gozo dentro do período concessivo.</p>
    <div class="tabela"><table>
        <tr><th>Colaborador</th><th>Setor</th><th>Aquisitivo</th><th class="num">Saldo</th><th>Concessivo até</th><th>Iniciar até</th><th>Nível</th><th></th></tr>
        @forelse($alertas as $a)
            <tr><td>{{ $a->colaborador->nome }}</td><td>{{ $a->colaborador->setor?->nome }}</td><td>{{ $a->periodo->rotulo }}</td>
                <td class="num">{{ $a->saldo }}</td><td>{{ $a->periodo->limite_concessivo->format('d/m/Y') }}</td>
                <td>{{ $a->ultimo_inicio->format('d/m/Y') }}<div class="muted">{{ $a->faltam < 0 ? 'há '.abs($a->faltam).' dias' : 'em '.$a->faltam.' dias' }}</div></td>
                <td><span class="selo {{ \App\Services\Ferias::NIVEIS[$a->nivel][1] }}">{{ \App\Services\Ferias::NIVEIS[$a->nivel][0] }}</span></td>
                <td class="num"><a href="{{ route('ferias.index', ['colaborador_id' => $a->colaborador->id]) }}">Programar</a></td></tr>
        @empty
            <tr><td colspan="8" class="muted">Nenhum período perto de vencer.</td></tr>
        @endforelse
    </table></div>
</div>

<div class="card">
    <h2>Férias nos próximos 90 dias</h2>
    <div class="tabela"><table>
        <tr><th>Colaborador</th><th>Setor</th><th>Gozo</th><th class="num">Dias</th><th class="num">Venda</th><th>Situação</th></tr>
        @forelse($proximas as $p)
            <tr><td>{{ $p->colaborador->nome }}</td><td>{{ $p->colaborador->setor?->nome }}</td>
                <td>{{ $p->inicio->format('d/m/Y') }} a {{ $p->fim->format('d/m/Y') }}</td><td class="num">{{ $p->dias }}</td><td class="num">{{ $p->dias_abono ?: '' }}</td>
                <td><span class="selo {{ $p->selo }}">{{ $p->situacao }}</span></td></tr>
        @empty
            <tr><td colspan="6" class="muted">Nenhuma férias programada para os próximos 90 dias.</td></tr>
        @endforelse
    </table></div>
</div>
@endsection
