@extends('layouts.app')
@section('titulo', 'Dossiês e vencimentos')
@section('conteudo')
<form class="filtros" method="get" action="{{ route('documentos.index') }}">
    <div style="min-width:280px"><label>Abrir dossiê de</label><select name="colaborador_id" onchange="this.form.submit()"><option value="">Selecione...</option>
        @foreach(\App\Models\Colaborador::where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'matricula']) as $c)<option value="{{ $c->id }}">{{ $c->nome }} ({{ $c->matricula }})</option>@endforeach</select></div>
    <div><a class="btn sec" href="{{ route('tipos-documento.index') }}">Tipos de documento</a></div>
</form>

<div class="grade">
    <div class="kpi"><span>Vencidos</span><strong class="neg">{{ $vencendo->filter(fn ($d) => $d->situacao() === 'vencido')->count() }}</strong></div>
    <div class="kpi"><span>A vencer</span><strong>{{ $vencendo->filter(fn ($d) => $d->situacao() === 'vencendo')->count() }}</strong></div>
    <div class="kpi"><span>Colaboradores com pendência</span><strong>{{ $faltando->count() }}</strong><div class="muted">documento obrigatório faltando</div></div>
    <div class="kpi"><span>A conferir</span><strong>{{ $pendentesConferencia->count() }}</strong><div class="muted">enviados pelo portal</div></div>
</div>

<div class="card">
    <h2>Vencidos e a vencer</h2>
    <div class="tabela"><table>
        <tr><th>Colaborador</th><th>Documento</th><th>Validade</th><th>Situação</th><th></th></tr>
        @forelse($vencendo as $d)
            @php([$txt, $cls] = \App\Models\Documento::SITUACOES[$d->situacao()])
            <tr><td>{{ $d->colaborador->nome }}<div class="muted">{{ $d->colaborador->setor?->nome }}</div></td><td>{{ $d->tipo->nome }}</td>
                <td>{{ $d->validade->format('d/m/Y') }}<div class="muted">{{ $d->validade->isPast() ? 'venceu há '.$d->validade->diffInDays(today()).' dias' : 'vence em '.today()->diffInDays($d->validade).' dias' }}</div></td>
                <td><span class="selo {{ $cls }}">{{ $txt }}</span></td>
                <td class="num"><a href="{{ route('documentos.index', ['colaborador_id' => $d->colaborador_id]) }}">Dossiê</a></td></tr>
        @empty
            <tr><td colspan="5" class="muted">Nenhum documento vencido ou perto de vencer.</td></tr>
        @endforelse
    </table></div>
</div>

<div class="card">
    <h2>Obrigatórios faltando</h2>
    <div class="tabela"><table>
        <tr><th>Colaborador</th><th>Faltam</th><th></th></tr>
        @forelse($faltando as $f)
            <tr><td>{{ $f->colaborador->nome }}</td><td>{{ $f->tipos->pluck('nome')->implode(', ') }}</td>
                <td class="num"><a href="{{ route('documentos.index', ['colaborador_id' => $f->colaborador->id]) }}">Dossiê</a></td></tr>
        @empty
            <tr><td colspan="3" class="muted">Todos os colaboradores ativos têm os documentos obrigatórios.</td></tr>
        @endforelse
    </table></div>
</div>

@if($pendentesConferencia->isNotEmpty())
<div class="card">
    <h2>Enviados pelo portal, aguardando conferência</h2>
    <div class="tabela"><table>
        <tr><th>Enviado</th><th>Colaborador</th><th>Documento</th><th></th></tr>
        @foreach($pendentesConferencia as $d)
            <tr><td>{{ $d->created_at->format('d/m/Y H:i') }}</td><td>{{ $d->colaborador->nome }}</td>
                <td><a href="{{ route('documentos.baixar', [$d, 'ver' => 1]) }}" target="_blank">{{ $d->tipo->nome }}</a></td>
                <td class="num"><form class="inline" method="post" action="{{ route('documentos.conferir', $d) }}">@csrf<button class="btn peq">Conferir</button></form></td></tr>
        @endforeach
    </table></div>
</div>
@endif
@endsection
