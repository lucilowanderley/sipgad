@extends('layouts.app')
@section('titulo', 'Mural de comunicados')
@section('conteudo')
@if(auth()->user()->ehGestaoPessoal())
    <p><a class="btn" href="{{ route('mural.create') }}">Novo comunicado</a></p>
@endif
<div class="lista-cards">
    @forelse($comunicados as $c)
        @include('mural._item')
    @empty
        <div class="card muted">Nenhum comunicado publicado.</div>
    @endforelse
</div>
<div class="paginacao">{{ $comunicados->links() }}</div>

@if($gerenciar->isNotEmpty())
    <div class="card" style="margin-top:18px">
        <h2>Agendados e expirados</h2>
        <div class="tabela"><table>
            <tr><th>Título</th><th>Publicação</th><th>Expira</th><th></th></tr>
            @foreach($gerenciar as $c)
                <tr><td><a href="{{ route('mural.show', $c) }}">{{ $c->titulo }}</a></td><td>{{ $c->publicar_em->format('d/m/Y H:i') }}
                    @if($c->publicar_em->isFuture())<span class="selo alerta">Agendado</span>@endif</td>
                    <td>{{ $c->expira_em?->format('d/m/Y') }}@if($c->expira_em?->lt(today()))<span class="selo">Expirado</span>@endif</td>
                    <td class="num"><a class="btn sec peq" href="{{ route('mural.edit', $c) }}">Editar</a></td></tr>
            @endforeach
        </table></div>
    </div>
@endif
@endsection
