@extends('layouts.app')
@section('titulo', ($item->exists ? 'Editar ' : 'Novo ').$cfg['singular'])
@section('conteudo')
<form method="post" action="{{ $item->exists ? route("$recurso.update", $item->id) : route("$recurso.store") }}" class="card">
    @csrf
    @if($item->exists) @method('put') @endif
    <div class="campos">
        @foreach($cfg['campos'] as $campo => $def)
            @php($tipo = $def['tipo'] ?? 'text')
            @php($valor = old($campo, $item->exists ? $item->$campo : ($def['padrao'] ?? null)))
            <div class="campo">
                @if($tipo === 'checkbox')
                    <label style="margin-top:24px"><input type="checkbox" name="{{ $campo }}" value="1" @checked($valor)> {{ $def['label'] }}</label>
                @else
                    <label for="{{ $campo }}">{{ $def['label'] }}</label>
                    @if($tipo === 'select')
                        <select id="{{ $campo }}" name="{{ $campo }}">
                            <option value="">—</option>
                            @foreach(($def['opcoes'])() as $id => $nome)
                                <option value="{{ $id }}" @selected((string) $valor === (string) $id)>{{ $nome }}</option>
                            @endforeach
                        </select>
                    @else
                        <input id="{{ $campo }}" type="{{ $tipo }}" name="{{ $campo }}"
                               value="{{ $valor instanceof \Carbon\CarbonInterface ? $valor->format('Y-m-d') : $valor }}">
                    @endif
                @endif
                @error($campo)<div class="erro-campo">{{ $message }}</div>@enderror
            </div>
        @endforeach
    </div>
    <p style="margin-top:18px">
        <button class="btn">Salvar</button>
        <a class="btn sec" href="{{ route("$recurso.index") }}">Cancelar</a>
    </p>
</form>
@endsection
