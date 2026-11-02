@extends('layouts.app')
@section('titulo', 'Leituras do comunicado')
@section('conteudo')
<p><a href="{{ route('mural.show', $comunicado) }}">← {{ $comunicado->titulo }}</a></p>
<div class="grade">
    <div class="kpi"><span>Público</span><strong>{{ $publico->count() }}</strong><div class="muted">{{ $comunicado->publico_texto }}</div></div>
    <div class="kpi"><span>Leram</span><strong>{{ $publico->filter(fn ($u) => $leituras->has($u->id))->count() }}</strong></div>
    @if($comunicado->exige_ciencia)<div class="kpi"><span>Deram ciência</span><strong>{{ $publico->filter(fn ($u) => $leituras->get($u->id)?->ciente_em)->count() }}</strong></div>@endif
    <div class="kpi"><span>Pendentes</span><strong class="{{ $pendentes->count() ? 'neg' : 'pos' }}">{{ $pendentes->count() }}</strong></div>
</div>
<div class="tabela"><table>
    <tr><th>Usuário</th><th>Setor</th><th>Leu em</th>@if($comunicado->exige_ciencia)<th>Ciência em</th>@endif</tr>
    @foreach($publico as $u)
        @php($l = $leituras->get($u->id))
        <tr><td>{{ $u->name }}</td><td>{{ $u->colaborador?->setor?->nome }}</td>
            <td>{!! $l ? e($l->lido_em->format('d/m/Y H:i')) : '<span class="selo alerta">Não leu</span>' !!}</td>
            @if($comunicado->exige_ciencia)<td>{!! $l?->ciente_em ? e($l->ciente_em->format('d/m/Y H:i')) : '<span class="selo erro">Pendente</span>' !!}</td>@endif</tr>
    @endforeach
</table></div>
@endsection
