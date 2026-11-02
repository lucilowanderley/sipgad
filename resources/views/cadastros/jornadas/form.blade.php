@extends('layouts.app')
@section('titulo', $jornada->exists ? 'Editar jornada' : 'Nova jornada')
@section('conteudo')
@php($tipo = old('tipo', $jornada->tipo))
<form method="post" action="{{ $jornada->exists ? route('jornadas.update', $jornada) : route('jornadas.store') }}" class="card">
    @csrf
    @if($jornada->exists) @method('put') @endif
    <div class="campos">
        <div class="campo"><label>Empresa</label>
            <select name="empresa_id">@foreach($empresas as $e)<option value="{{ $e->id }}" @selected(old('empresa_id', $jornada->empresa_id) == $e->id)>{{ $e->nome }}</option>@endforeach</select></div>
        <div class="campo"><label>Nome</label><input name="nome" value="{{ old('nome', $jornada->nome) }}" placeholder="Ex.: Comercial 44h">
            @error('nome')<div class="erro-campo">{{ $message }}</div>@enderror</div>
        <div class="campo"><label>Tipo</label>
            <select name="tipo" id="tipo" onchange="trocarTipo()">
                <option value="semanal" @selected($tipo === 'semanal')>Semanal (horário por dia da semana)</option>
                <option value="revezamento" @selected($tipo === 'revezamento')>Revezamento (ex.: 12x36)</option>
            </select></div>
    </div>

    <div id="bloco-semanal" style="margin-top:18px">
        <p class="muted">Deixe a entrada vazia nos dias de folga. Saída menor que a entrada é tratada como dia seguinte (turno noturno).</p>
        <div class="tabela"><table>
            <tr><th>Dia</th><th>Entrada</th><th>Saída intervalo</th><th>Volta intervalo</th><th>Saída</th></tr>
            @foreach(\App\Models\Jornada::DIAS as $n => $nome)
                @php($d = $jornada->dias->firstWhere('dia_semana', $n))
                <tr>
                    <td>{{ $nome }}</td>
                    @foreach(['entrada1', 'saida1', 'entrada2', 'saida2'] as $c)
                        <td><input type="time" name="dias[{{ $n }}][{{ $c }}]" value="{{ old("dias.$n.$c", $d ? substr((string) $d->$c, 0, 5) : '') }}">
                            @error("dias.$n.$c")<div class="erro-campo">{{ $message }}</div>@enderror</td>
                    @endforeach
                </tr>
            @endforeach
        </table></div>
        <p><button type="button" class="btn sec peq" onclick="copiarSegunda()">Copiar segunda para terça a sexta</button></p>
    </div>

    <div id="bloco-revezamento" class="campos" style="margin-top:18px">
        <div class="campo"><label>Horas de trabalho</label><input type="number" name="horas_trabalho" value="{{ old('horas_trabalho', $jornada->horas_trabalho ?? 12) }}"></div>
        <div class="campo"><label>Horas de folga</label><input type="number" name="horas_folga" value="{{ old('horas_folga', $jornada->horas_folga ?? 36) }}"></div>
        <div class="campo"><label>Entrada</label><input type="time" name="entrada" value="{{ old('entrada', substr((string) $jornada->entrada, 0, 5)) }}">
            @error('entrada')<div class="erro-campo">{{ $message }}</div>@enderror</div>
        <div class="campo"><label>Intervalo (minutos)</label><input type="number" name="intervalo_minutos" value="{{ old('intervalo_minutos', $jornada->intervalo_minutos ?? 60) }}"></div>
        <p class="muted" style="grid-column:1/-1">O primeiro dia de trabalho do ciclo é informado no cadastro de cada colaborador.</p>
    </div>

    <p style="margin-top:18px"><button class="btn">Salvar</button> <a class="btn sec" href="{{ route('jornadas.index') }}">Cancelar</a></p>
</form>
@push('scripts')
<script>
function trocarTipo(){const t=document.getElementById('tipo').value;document.getElementById('bloco-semanal').style.display=t==='semanal'?'':'none';document.getElementById('bloco-revezamento').style.display=t==='revezamento'?'':'none';}
function copiarSegunda(){['entrada1','saida1','entrada2','saida2'].forEach(c=>{const v=document.querySelector(`[name="dias[1][${c}]"]`).value;for(let d=2;d<=5;d++)document.querySelector(`[name="dias[${d}][${c}]"]`).value=v;});}
trocarTipo();
</script>
@endpush
@endsection
