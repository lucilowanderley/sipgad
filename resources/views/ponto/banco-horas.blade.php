@extends('layouts.app')
@section('titulo', 'Banco de horas')
@section('conteudo')
@php($h = fn ($m) => \App\Services\Apuracao::hhmm((int) $m, true))
<p class="muted">Saldos consolidados nos fechamentos mensais, mais compensações, pagamentos e ajustes lançados pelo DP. O mês em aberto aparece no espelho.</p>
<div class="tabela"><table>
    <tr><th>Colaborador</th><th>Setor</th><th class="num">Saldo</th><th></th></tr>
    @forelse($colaboradores as $c)
        <tr @if($selecionado?->id === $c->id) style="background:#f0fdfa" @endif>
            <td>{{ $c->nome }}</td><td>{{ $c->setor?->nome }}</td>
            <td class="num {{ $c->saldo < 0 ? 'neg' : 'pos' }}">{{ $h($c->saldo) }}</td>
            <td class="num"><a href="{{ route('banco-horas.index', ['colaborador_id' => $c->id]) }}">Extrato</a></td>
        </tr>
    @empty
        <tr><td colspan="4" class="muted">Nenhum colaborador.</td></tr>
    @endforelse
</table></div>

@if($selecionado)
    <div class="card" style="margin-top:18px">
        <h2>Extrato de {{ $selecionado->nome }}</h2>
        <div class="tabela"><table>
            <tr><th>Data</th><th>Tipo</th><th>Descrição</th><th class="num">Horas</th></tr>
            @forelse($lancamentos as $l)
                <tr><td>{{ $l->data->format('d/m/Y') }}</td><td>{{ \App\Models\BancoHorasLancamento::TIPOS[$l->tipo] ?? $l->tipo }}</td><td>{{ $l->descricao }}</td>
                    <td class="num {{ $l->minutos < 0 ? 'neg' : 'pos' }}">{{ $h($l->minutos) }}</td></tr>
            @empty
                <tr><td colspan="4" class="muted">Sem lançamentos.</td></tr>
            @endforelse
        </table></div>

        @if(auth()->user()->temPerfil('admin', 'dp'))
            <h2 style="margin-top:18px">Novo lançamento</h2>
            <form method="post" action="{{ route('banco-horas.lancar') }}" class="filtros">
                @csrf <input type="hidden" name="colaborador_id" value="{{ $selecionado->id }}">
                <div><label>Data</label><input type="date" name="data" value="{{ old('data', today()->format('Y-m-d')) }}" required></div>
                <div><label>Tipo</label><select name="tipo">
                    <option value="compensacao">Compensação (folga)</option><option value="pagamento">Pagamento em folha</option><option value="ajuste">Ajuste manual</option></select></div>
                <div style="min-width:90px"><label>Sinal</label><select name="sinal"><option value="-">− Débito</option><option value="+">+ Crédito</option></select></div>
                <div style="min-width:100px"><label>Horas</label><input name="horas" placeholder="08:00" value="{{ old('horas') }}" required>@error('horas')<div class="erro-campo">{{ $message }}</div>@enderror</div>
                <div style="flex:1"><label>Descrição</label><input name="descricao" value="{{ old('descricao') }}" required></div>
                <div><button class="btn">Lançar</button></div>
            </form>
        @endif
    </div>
@endif
@endsection
