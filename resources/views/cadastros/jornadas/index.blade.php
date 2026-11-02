@extends('layouts.app')
@section('titulo', 'Jornadas e escalas')
@section('conteudo')
<p><a class="btn" href="{{ route('jornadas.create') }}">Nova jornada</a></p>
<div class="tabela"><table>
    <tr><th>Nome</th><th>Tipo</th><th>Horários</th><th>Empresa</th><th></th></tr>
    @forelse($jornadas as $j)
        <tr>
            <td>{{ $j->nome }}</td>
            <td>{{ $j->tipo === 'revezamento' ? 'Revezamento' : 'Semanal' }}</td>
            <td class="muted">{{ $j->descricao() }}</td>
            <td>{{ $j->empresa?->nome }}</td>
            <td class="num" style="white-space:nowrap">
                <a class="btn sec peq" href="{{ route('jornadas.edit', $j) }}">Editar</a>
                <form class="inline" method="post" action="{{ route('jornadas.destroy', $j) }}" onsubmit="return confirm('Excluir esta jornada?')">@csrf @method('delete')<button class="btn perigo peq">Excluir</button></form>
            </td>
        </tr>
    @empty
        <tr><td colspan="5" class="muted">Nenhuma jornada cadastrada.</td></tr>
    @endforelse
</table></div>
@endsection
