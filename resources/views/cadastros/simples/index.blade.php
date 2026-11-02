@extends('layouts.app')
@section('titulo', $cfg['titulo'])
@section('conteudo')
<p><a class="btn" href="{{ route("$recurso.create") }}">Novo {{ $cfg['singular'] }}</a></p>
<div class="tabela"><table>
    <tr>@foreach($cfg['colunas'] as $label)<th>{{ $label }}</th>@endforeach<th></th></tr>
    @forelse($itens as $item)
        <tr>
            @foreach(array_keys($cfg['colunas']) as $col)
                @php($v = data_get($item, $col))
                <td>{{ $v instanceof \Carbon\CarbonInterface ? $v->format('d/m/Y') : (is_bool($v) ? ($v ? 'Sim' : 'Não') : $v) }}</td>
            @endforeach
            <td class="num" style="white-space:nowrap">
                <a class="btn sec peq" href="{{ route("$recurso.edit", $item->id) }}">Editar</a>
                <form class="inline" method="post" action="{{ route("$recurso.destroy", $item->id) }}" onsubmit="return confirm('Excluir este registro?')">
                    @csrf @method('delete')<button class="btn perigo peq">Excluir</button>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="{{ count($cfg['colunas']) + 1 }}" class="muted">Nenhum registro.</td></tr>
    @endforelse
</table></div>
<div class="paginacao">{{ $itens->links() }}</div>
@endsection
