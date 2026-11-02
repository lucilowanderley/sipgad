@extends('layouts.app')
@section('titulo', 'Colaboradores')
@section('conteudo')
<form class="filtros" method="get">
    <div><label>Buscar</label><input name="busca" value="{{ request('busca') }}" placeholder="Nome, matrícula ou CPF"></div>
    <div><label>Setor</label><select name="setor_id"><option value="">Todos</option>
        @foreach($setores as $s)<option value="{{ $s->id }}" @selected(request('setor_id') == $s->id)>{{ $s->nome }}</option>@endforeach</select></div>
    <div><label>Situação</label><select name="situacao">
        <option value="ativos">Ativos</option>
        <option value="inativos" @selected(request('situacao') === 'inativos')>Desligados</option>
        <option value="todos" @selected(request('situacao') === 'todos')>Todos</option></select></div>
    <div><button class="btn sec">Filtrar</button></div>
    <div style="margin-left:auto"><a class="btn" href="{{ route('colaboradores.create') }}">Novo colaborador</a></div>
</form>
<div class="tabela"><table>
    <tr><th>Matrícula</th><th>Nome</th><th>CPF</th><th>Setor</th><th>Cargo</th><th>Admissão</th><th>Situação</th><th></th></tr>
    @forelse($colaboradores as $c)
        <tr>
            <td>{{ $c->matricula }}</td><td>{{ $c->nome }}</td><td>{{ $c->cpf }}</td>
            <td>{{ $c->setor?->nome }}</td><td>{{ $c->cargo?->nome }}</td><td>{{ $c->data_admissao->format('d/m/Y') }}</td>
            <td>@if($c->ativo)<span class="selo ok">Ativo</span>@else<span class="selo">Desligado</span>@endif</td>
            <td class="num" style="white-space:nowrap">
                <a class="btn sec peq" href="{{ route('espelho', ['colaborador_id' => $c->id]) }}">Espelho</a>
                <a class="btn sec peq" href="{{ route('colaboradores.edit', $c) }}">Editar</a>
            </td>
        </tr>
    @empty
        <tr><td colspan="8" class="muted">Nenhum colaborador encontrado.</td></tr>
    @endforelse
</table></div>
<div class="paginacao">{{ $colaboradores->links() }}</div>
@endsection
