@extends('layouts.app')
@section('titulo', 'Solicitações')
@section('conteudo')
@php($u = auth()->user())
<div class="card">
    <h2>Nova solicitação</h2>
    <form method="post" action="{{ route('solicitacoes.store') }}" enctype="multipart/form-data" id="form-solicitacao">
        @csrf
        <div class="campos">
            @if($colaboradores->isNotEmpty())
                <div class="campo"><label>Colaborador</label>
                    <select name="colaborador_id">
                        @if($u->colaborador)<option value="">Eu mesmo ({{ $u->colaborador->nome }})</option>@endif
                        @foreach($colaboradores as $c)<option value="{{ $c->id }}" @selected(old('colaborador_id') == $c->id)>{{ $c->nome }} ({{ $c->matricula }})</option>@endforeach
                    </select></div>
            @endif
            <div class="campo"><label>Tipo</label>
                <select name="tipo" id="tipo">
                    @foreach(\App\Models\Solicitacao::TIPOS as $k => $v)<option value="{{ $k }}" @selected(old('tipo', request('tipo')) === $k)>{{ $v }}</option>@endforeach
                </select></div>
            <div class="campo"><label id="rotulo-data">Data</label><input type="date" name="data" value="{{ old('data', request('data')) }}" required>@error('data')<div class="erro-campo">{{ $message }}</div>@enderror</div>
            <div class="campo so-ajuste"><label>Horário a incluir</label><input type="time" name="hora" value="{{ old('hora') }}">@error('hora')<div class="erro-campo">{{ $message }}</div>@enderror</div>
            <div class="campo so-abono"><label>Abrange</label>
                <select name="periodo" id="periodo">
                    <option value="dia" @selected(old('periodo') !== 'parcial')>Dia(s) inteiro(s)</option>
                    <option value="parcial" @selected(old('periodo') === 'parcial')>Parte do dia (horas)</option>
                </select></div>
            <div class="campo so-abono so-dia"><label>Até (opcional, vários dias)</label><input type="date" name="data_fim" value="{{ old('data_fim') }}">@error('data_fim')<div class="erro-campo">{{ $message }}</div>@enderror</div>
            <div class="campo so-abono so-parcial"><label>Horas a abonar</label><input name="horas" placeholder="02:30" value="{{ old('horas') }}">@error('horas')<div class="erro-campo">{{ $message }}</div>@enderror</div>
            <div class="campo so-atestado"><label>CID (opcional)</label><input name="cid" maxlength="10" value="{{ old('cid') }}"></div>
            <div class="campo so-anexo"><label id="rotulo-anexo">Anexo (opcional)</label><input type="file" name="anexo" accept=".pdf,image/*">@error('anexo')<div class="erro-campo">{{ $message }}</div>@enderror</div>
        </div>
        <div class="campo" style="margin-top:12px"><label>Motivo / justificativa</label>
            <textarea name="motivo" rows="3" required minlength="5" placeholder="Ex.: esqueci de registrar a saída do almoço; estava em reunião externa...">{{ old('motivo') }}</textarea>
            @error('motivo')<div class="erro-campo">{{ $message }}</div>@enderror</div>
        <p style="margin-top:14px;display:flex;gap:14px;align-items:center;flex-wrap:wrap">
            <button class="btn">Enviar solicitação</button>
            @if($u->ehGestaoPessoal())
                <label style="margin:0"><input type="checkbox" name="aprovar_direto" value="1"> Já aprovar (lançamento do RH/DP para outro colaborador)</label>
            @endif
        </p>
        <p class="muted">O pedido vai para o seu gestor direto; sem gestor cadastrado, vai para o RH/DP. Atestados entram no espelho como dia abonado depois de aprovados.</p>
    </form>
</div>

@if($proprias)
    <h2 style="font-size:16px">Minhas solicitações</h2>
    <div class="tabela"><table>
        <tr><th>Enviada</th><th>Tipo</th><th>Período</th><th>Motivo</th><th>Situação</th><th></th></tr>
        @forelse($proprias as $s)
            <tr>
                <td style="white-space:nowrap">{{ $s->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ \App\Models\Solicitacao::TIPOS[$s->tipo] }}</td>
                <td>{{ $s->periodo_texto }}</td>
                <td>{{ \Illuminate\Support\Str::limit($s->motivo, 80) }}
                    @if($s->anexo_path)<br><a href="{{ route('solicitacoes.anexo', $s) }}" target="_blank">ver anexo</a>@endif</td>
                <td>@include('portal._status', ['status' => $s->status])
                    @if($s->decidido_em && $s->status !== 'pendente')<div class="muted">{{ $s->decisor?->name }} em {{ $s->decidido_em->format('d/m H:i') }}</div>@endif
                    @if($s->resposta)<div class="muted">“{{ $s->resposta }}”</div>@endif</td>
                <td class="num">@if($s->status === 'pendente')
                    <form method="post" action="{{ route('solicitacoes.cancelar', $s) }}" onsubmit="return confirm('Cancelar esta solicitação?')">@csrf<button class="btn sec peq">Cancelar</button></form>
                @endif</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">Você ainda não fez solicitações.</td></tr>
        @endforelse
    </table></div>
    <div class="paginacao">{{ $proprias->links() }}</div>
@endif
@endsection
@push('scripts')
<script>
(function () {
    const tipo = document.getElementById('tipo'), periodo = document.getElementById('periodo');
    function atualizar() {
        const t = tipo.value, p = periodo.value;
        document.querySelectorAll('.so-ajuste').forEach(e => e.classList.toggle('oculto', t !== 'ajuste'));
        document.querySelectorAll('.so-abono').forEach(e => e.classList.toggle('oculto', t === 'ajuste'));
        document.querySelectorAll('.so-atestado').forEach(e => e.classList.toggle('oculto', t !== 'atestado'));
        document.querySelectorAll('.so-dia').forEach(e => e.classList.toggle('oculto', t === 'ajuste' || p !== 'dia'));
        document.querySelectorAll('.so-parcial').forEach(e => e.classList.toggle('oculto', t === 'ajuste' || p !== 'parcial'));
        document.getElementById('rotulo-data').textContent = t === 'ajuste' ? 'Dia da batida esquecida' : 'Data (início)';
        document.getElementById('rotulo-anexo').textContent = t === 'atestado' ? 'Atestado (foto ou PDF, obrigatório)' : 'Anexo (opcional)';
    }
    tipo.addEventListener('change', atualizar); periodo.addEventListener('change', atualizar); atualizar();
})();
</script>
@endpush
