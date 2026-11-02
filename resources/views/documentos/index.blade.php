@extends('layouts.app')
@section('titulo', 'Dossiê digital')
@section('conteudo')
@php($u = auth()->user())
@php($kb = fn ($b) => $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.').' MB' : max(1, round($b / 1024)).' KB')
@if($colaboradores->isNotEmpty())
    <form class="filtros" method="get">
        <div style="min-width:280px"><label>Colaborador</label><select name="colaborador_id" onchange="this.form.submit()">
            @foreach($colaboradores as $c)<option value="{{ $c->id }}" @selected($colaborador->id === $c->id)>{{ $c->nome }} ({{ $c->matricula }})</option>@endforeach</select></div>
        <div><a class="btn sec" href="{{ route('documentos.vencimentos') }}">Vencimentos de todos</a></div>
    </form>
@endif

<div class="grade">
    <div class="kpi"><span>Colaborador</span><strong style="font-size:17px">{{ $colaborador->nome }}</strong><div class="muted">matrícula {{ $colaborador->matricula }}</div></div>
    <div class="kpi"><span>Documentos</span><strong>{{ $vigentes->count() }}</strong><div class="muted">{{ $documentos->count() }} arquivos no histórico</div></div>
    <div class="kpi"><span>Vencidos / a vencer</span><strong class="{{ $resumo->whereIn('situacao', ['vencido', 'vencendo'])->count() ? 'neg' : '' }}">{{ $resumo->where('situacao', 'vencido')->count() }} / {{ $resumo->where('situacao', 'vencendo')->count() }}</strong></div>
    <div class="kpi"><span>Obrigatórios faltando</span><strong class="{{ $resumo->where('situacao', 'faltando')->count() ? 'neg' : 'pos' }}">{{ $resumo->where('situacao', 'faltando')->count() }}</strong></div>
</div>

<div class="card">
    <h2>Situação por tipo</h2>
    <div class="tabela"><table>
        <tr><th>Tipo</th><th>Arquivo atual</th><th>Validade</th><th>Situação</th></tr>
        @foreach($resumo->filter(fn ($r) => $r->doc || $r->tipo->obrigatorio) as $r)
            <tr><td>{{ $r->tipo->nome }}@if($r->tipo->obrigatorio) <span class="muted">(obrigatório)</span>@endif</td>
                <td>@if($r->doc)<a href="{{ route('documentos.baixar', [$r->doc, 'ver' => 1]) }}" target="_blank">{{ $r->doc->arquivo_nome }}</a>@else<span class="muted">—</span>@endif</td>
                <td>{{ $r->doc?->validade?->format('d/m/Y') }}</td>
                <td>@if($r->situacao === 'faltando')<span class="selo erro">Faltando</span>
                    @elseif($r->situacao)@php([$txt, $cls] = \App\Models\Documento::SITUACOES[$r->situacao])<span class="selo {{ $cls }}">{{ $txt }}</span>@endif</td></tr>
        @endforeach
    </table></div>
</div>

@if($tiposEnvio->isNotEmpty())
<div class="card">
    <h2>Enviar documento</h2>
    <form method="post" action="{{ route('documentos.store') }}" enctype="multipart/form-data">
        @csrf <input type="hidden" name="colaborador_id" value="{{ $colaborador->id }}">
        <div class="campos">
            <div class="campo"><label>Tipo</label><select name="tipo_documento_id" id="d-tipo" required>
                @foreach($tiposEnvio as $t)<option value="{{ $t->id }}" data-validade="{{ $t->exige_validade ? 1 : 0 }}" @selected(old('tipo_documento_id') == $t->id)>{{ $t->nome }}</option>@endforeach</select></div>
            <div class="campo"><label>Número (opcional)</label><input name="numero" maxlength="60" value="{{ old('numero') }}"></div>
            <div class="campo"><label>Emissão</label><input type="date" name="emissao" value="{{ old('emissao') }}">@error('emissao')<div class="erro-campo">{{ $message }}</div>@enderror</div>
            <div class="campo"><label id="d-rot-validade">Validade</label><input type="date" name="validade" value="{{ old('validade') }}">@error('validade')<div class="erro-campo">{{ $message }}</div>@enderror</div>
            <div class="campo"><label>Arquivo (PDF, imagem, Word; até 15 MB)</label><input type="file" name="arquivo" required>@error('arquivo')<div class="erro-campo">{{ $message }}</div>@enderror</div>
            <div class="campo"><label>Descrição (opcional)</label><input name="descricao" maxlength="255" value="{{ old('descricao') }}"></div>
        </div>
        <p style="margin-top:14px"><button class="btn">Anexar ao dossiê</button></p>
        @unless($u->ehGestaoPessoal())<p class="muted">O RH confere os documentos enviados pelo portal.</p>@endunless
    </form>
</div>
@endif

<h2 style="font-size:16px">Histórico de arquivos</h2>
<div class="tabela"><table>
    <tr><th>Tipo</th><th>Arquivo</th><th>Emissão</th><th>Validade</th><th>Enviado</th><th>Conferência</th><th></th></tr>
    @forelse($documentos as $d)
        @php($vigente = $vigentes->get($d->tipo_documento_id)?->id === $d->id)
        <tr @class(['fds' => ! $vigente])>
            <td>{{ $d->tipo->nome }}@unless($vigente)<div class="muted">substituído</div>@endunless</td>
            <td><a href="{{ route('documentos.baixar', [$d, 'ver' => 1]) }}" target="_blank">{{ $d->arquivo_nome }}</a>
                <div class="muted">{{ $kb($d->tamanho) }}@if($d->numero) · nº {{ $d->numero }}@endif @if($d->descricao) · {{ $d->descricao }}@endif</div></td>
            <td>{{ $d->emissao?->format('d/m/Y') }}</td>
            <td>{{ $d->validade?->format('d/m/Y') }}@if($vigente && in_array($d->situacao(), ['vencido', 'vencendo']))@php([$txt, $cls] = \App\Models\Documento::SITUACOES[$d->situacao()]) <span class="selo {{ $cls }}">{{ $txt }}</span>@endif</td>
            <td>{{ $d->created_at->format('d/m/Y') }}<div class="muted">{{ $d->remetente?->name }}</div></td>
            <td>@if($d->conferido_em)<span class="selo ok">Conferido</span>@else<span class="selo alerta">A conferir</span>@endif</td>
            <td class="num" style="white-space:nowrap">
                <a class="btn sec peq" href="{{ route('documentos.baixar', $d) }}">Baixar</a>
                @if($u->ehGestaoPessoal())
                    @unless($d->conferido_em)<form class="inline" method="post" action="{{ route('documentos.conferir', $d) }}">@csrf<button class="btn peq">Conferir</button></form>@endunless
                    <form class="inline" method="post" action="{{ route('documentos.destroy', $d) }}" onsubmit="return confirm('Remover este documento do dossiê?')">@csrf @method('delete')<button class="btn perigo peq">Remover</button></form>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="muted">Nenhum documento anexado.</td></tr>
    @endforelse
</table></div>
@endsection
@push('scripts')
<script>
(function () {
    const s = document.getElementById('d-tipo'); if (!s) return;
    const r = document.getElementById('d-rot-validade');
    const f = () => { const v = s.selectedOptions[0]?.dataset.validade === '1'; r.textContent = v ? 'Validade (obrigatória)' : 'Validade (se houver)'; };
    s.addEventListener('change', f); f();
})();
</script>
@endpush
