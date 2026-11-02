@extends('layouts.app')
@section('titulo', 'Fechamento de competência')
@section('conteudo')
<div class="card">
    <h2>Fechar competência</h2>
    <p class="muted">Ao fechar, as marcações do mês ficam bloqueadas para ajuste e, se a empresa usa banco de horas, o saldo de cada colaborador é lançado no banco. Revise os espelhos antes.</p>
    <form method="post" action="{{ route('fechamentos.fechar') }}" class="filtros" onsubmit="return confirm('Confirmar o fechamento?')">
        @csrf
        <div style="min-width:240px"><label>Empresa</label><select name="empresa_id">@foreach($empresas as $e)<option value="{{ $e->id }}">{{ $e->nome }}</option>@endforeach</select></div>
        <div><label>Competência</label><input type="month" name="competencia" value="{{ now()->subMonth()->format('Y-m') }}" required></div>
        <div><button class="btn">Fechar</button></div>
    </form>
</div>
<div class="tabela"><table>
    <tr><th>Competência</th><th>Empresa</th><th>Fechada em</th><th>Por</th><th></th></tr>
    @forelse($periodos as $p)
        <tr><td>{{ $p->inicio->format('m/Y') }}</td><td>{{ $p->empresa->nome }}</td><td>{{ $p->fechado_em->format('d/m/Y H:i') }}</td><td>{{ $p->responsavel?->name }}</td>
            <td class="num">@if(auth()->user()->temPerfil('admin'))
                <form class="inline" method="post" action="{{ route('fechamentos.reabrir', $p) }}" onsubmit="return confirm('Reabrir apaga os lançamentos de banco de horas desta competência. Continuar?')">@csrf @method('delete')<button class="btn sec peq">Reabrir</button></form>
            @endif</td></tr>
    @empty
        <tr><td colspan="5" class="muted">Nenhuma competência fechada.</td></tr>
    @endforelse
</table></div>
@endsection
