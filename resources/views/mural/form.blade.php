@extends('layouts.app')
@section('titulo', $comunicado->exists ? 'Editar comunicado' : 'Novo comunicado')
@section('conteudo')
<form method="post" action="{{ $comunicado->exists ? route('mural.update', $comunicado) : route('mural.store') }}" class="card">
    @csrf @if($comunicado->exists) @method('put') @endif
    <div class="campo"><label>Título</label><input name="titulo" value="{{ old('titulo', $comunicado->titulo) }}" required maxlength="255">@error('titulo')<div class="erro-campo">{{ $message }}</div>@enderror</div>
    <div class="campo" style="margin-top:12px"><label>Texto</label><textarea name="corpo" rows="10" required>{{ old('corpo', $comunicado->corpo) }}</textarea>@error('corpo')<div class="erro-campo">{{ $message }}</div>@enderror</div>
    <div class="campos" style="margin-top:12px">
        <div class="campo"><label>Categoria</label><select name="categoria">
            @foreach(\App\Models\Comunicado::CATEGORIAS as $k => $v)<option value="{{ $k }}" @selected(old('categoria', $comunicado->categoria) === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="campo"><label>Empresa</label><select name="empresa_id"><option value="">Todas</option>
            @foreach($empresas as $e)<option value="{{ $e->id }}" @selected(old('empresa_id', $comunicado->empresa_id) == $e->id)>{{ $e->nome }}</option>@endforeach</select></div>
        <div class="campo"><label>Setor</label><select name="setor_id"><option value="">Todos</option>
            @foreach($setores as $s)<option value="{{ $s->id }}" @selected(old('setor_id', $comunicado->setor_id) == $s->id)>{{ $s->nome }} · {{ $s->empresa?->nome }}</option>@endforeach</select></div>
        <div class="campo"><label>Publicar em</label><input type="datetime-local" name="publicar_em" value="{{ old('publicar_em', $comunicado->publicar_em?->format('Y-m-d\TH:i')) }}" required>@error('publicar_em')<div class="erro-campo">{{ $message }}</div>@enderror</div>
        <div class="campo"><label>Expira em (opcional)</label><input type="date" name="expira_em" value="{{ old('expira_em', $comunicado->expira_em?->format('Y-m-d')) }}">@error('expira_em')<div class="erro-campo">{{ $message }}</div>@enderror</div>
    </div>
    <p style="display:flex;gap:18px;flex-wrap:wrap;margin-top:14px">
        <label style="margin:0"><input type="checkbox" name="fixado" value="1" @checked(old('fixado', $comunicado->fixado))> Fixar no topo</label>
        <label style="margin:0"><input type="checkbox" name="exige_ciencia" value="1" @checked(old('exige_ciencia', $comunicado->exige_ciencia))> Exigir confirmação de ciência</label>
    </p>
    <p><button class="btn">{{ $comunicado->exists ? 'Salvar' : 'Publicar' }}</button> <a class="btn sec" href="{{ route('mural.index') }}">Cancelar</a></p>
</form>
@endsection
