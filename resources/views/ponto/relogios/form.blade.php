@extends('layouts.app')
@section('titulo', $relogio->exists ? 'Editar relógio' : 'Novo relógio')
@section('conteudo')
<form method="post" action="{{ $relogio->exists ? route('relogios.update', $relogio) : route('relogios.store') }}" class="card">
    @csrf
    @if($relogio->exists) @method('put') @endif
    @php($v = fn ($c, $p = null) => old($c, $relogio->exists ? $relogio->$c : $p))
    <div class="campos">
        <div class="campo"><label>Nome / identificação</label><input name="nome" value="{{ $v('nome') }}" placeholder="Ex.: Relógio da portaria" required>@error('nome')<div class="erro-campo">{{ $message }}</div>@enderror</div>
        <div class="campo"><label>Empresa</label><select name="empresa_id">@foreach($empresas as $e)<option value="{{ $e->id }}" @selected((string) $v('empresa_id') === (string) $e->id)>{{ $e->nome }}</option>@endforeach</select></div>
        <div class="campo"><label>Fabricante</label><input name="fabricante" value="{{ $v('fabricante', 'Dimep') }}" required></div>
        <div class="campo"><label>Modelo</label><input name="modelo" value="{{ $v('modelo', 'SMART 671 S') }}"></div>
        <div class="campo"><label>Tipo de REP</label><select name="tipo_rep">@foreach(\App\Models\Relogio::TIPOS_REP as $k => $t)<option value="{{ $k }}" @selected($v('tipo_rep', 'C') === $k)>{{ $t }}</option>@endforeach</select></div>
        <div class="campo"><label>Nº de fabricação (17 dígitos)</label><input name="numero_fabricacao" value="{{ $v('numero_fabricacao') }}" placeholder="Etiqueta do relógio; preenchido no 1º AFD se vazio">@error('numero_fabricacao')<div class="erro-campo">{{ $message }}</div>@enderror</div>
        <div class="campo"><label>Local de instalação</label><input name="local" value="{{ $v('local') }}"></div>
        <div class="campo"><label>IP na rede</label><input name="ip" value="{{ $v('ip') }}" placeholder="Ex.: 192.168.0.50">@error('ip')<div class="erro-campo">{{ $message }}</div>@enderror</div>
        <div class="campo"><label>Porta</label><input name="porta" type="number" value="{{ $v('porta', 80) }}">@error('porta')<div class="erro-campo">{{ $message }}</div>@enderror</div>
        <div class="campo" style="grid-column:1/-1"><label>Pasta de coleta automática (opcional)</label><input name="pasta_afd" value="{{ $v('pasta_afd') }}" placeholder="Ex.: C:\coleta\dimep — todo AFD salvo nesta pasta é importado sozinho">
            <div class="muted">Usada pelo comando <code>php artisan relogios:coletar</code> (agendado a cada 5 minutos).</div></div>
        <div class="campo"><label style="margin-top:24px"><input type="checkbox" name="ativo" value="1" @checked($v('ativo', true))> Ativo</label></div>
    </div>
    <p style="margin-top:18px">
        <button class="btn">Salvar</button>
        <a class="btn sec" href="{{ $relogio->exists ? route('relogios.show', $relogio) : route('relogios.index') }}">Cancelar</a>
    </p>
</form>
@endsection
