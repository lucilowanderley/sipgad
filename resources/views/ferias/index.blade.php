@extends('layouts.app')
@section('titulo', 'Férias')
@section('conteudo')
@php($u = auth()->user())
@if($visiveis->count() > 1 || ($u->ehGestaoPessoal() && $visiveis->isNotEmpty()))
    <form class="filtros" method="get">
        <div style="min-width:280px"><label>Colaborador</label><select name="colaborador_id" onchange="this.form.submit()">
            @unless($colaborador)<option value="">Selecione...</option>@endunless
            @foreach($visiveis as $c)<option value="{{ $c->id }}" @selected($colaborador?->id === $c->id)>{{ $c->nome }} ({{ $c->matricula }})</option>@endforeach
        </select></div>
        <div><a class="btn sec" href="{{ route('ferias.cronograma', ['setor_id' => $colaborador?->setor_id]) }}">Cronograma do setor</a></div>
    </form>
@endif

@if(! $colaborador)
    <div class="card">Selecione um colaborador.</div>
@elseif($periodos->isEmpty())
    <div class="card">Nenhum período aquisitivo. Férias não se aplicam a contratos de estágio ou PJ.</div>
@else
    @php($disponiveis = $periodos->filter(fn ($p) => $p->saldo() > 0 && ! $p->perdido)->sortBy('inicio'))
    <div class="grade">
        <div class="kpi"><span>Colaborador</span><strong style="font-size:17px">{{ $colaborador->nome }}</strong><div class="muted">admissão {{ $colaborador->data_admissao->format('d/m/Y') }}</div></div>
        <div class="kpi"><span>Dias a programar</span><strong>{{ $disponiveis->filter->completo()->sum(fn ($p) => $p->saldo()) }}</strong><div class="muted">em períodos já completos</div></div>
        @php($proxima = $colaborador->feriasProgramacoes()->ativas()->whereDate('fim', '>=', today())->orderBy('inicio')->first())
        <div class="kpi"><span>Próximas férias</span><strong style="font-size:17px">{{ $proxima ? $proxima->inicio->format('d/m/Y') : '—' }}</strong><div class="muted">{{ $proxima ? $proxima->dias.' dias · '.$proxima->situacao : 'nenhuma programada' }}</div></div>
        @php($alerta = $alertas->first())
        <div class="kpi"><span>Vencimento</span>
            @if($alerta)<strong style="font-size:17px"><span class="selo {{ \App\Services\Ferias::NIVEIS[$alerta->nivel][1] }}">{{ \App\Services\Ferias::NIVEIS[$alerta->nivel][0] }}</span></strong>
                <div class="muted">iniciar até {{ $alerta->ultimo_inicio->format('d/m/Y') }}</div>
            @else<strong style="font-size:17px"><span class="selo ok">Em dia</span></strong>@endif</div>
    </div>

    @if($podeProgramar && $disponiveis->isNotEmpty())
        <div class="card">
            <h2>Programar férias</h2>
            <form method="post" action="{{ route('ferias.store') }}" id="form-ferias">
                @csrf
                <div class="campos">
                    <div class="campo"><label>Período aquisitivo</label><select name="periodo_id" id="f-periodo">
                        @foreach($disponiveis as $p)<option value="{{ $p->id }}" data-max-abono="{{ intdiv($p->dias_direito, 3) }}" @selected(old('periodo_id') == $p->id)>{{ $p->rotulo }} · saldo {{ $p->saldo() }} dias{{ $p->completo() ? '' : ' (em formação)' }}</option>@endforeach
                    </select></div>
                    <div class="campo"><label>Início do gozo</label><input type="date" name="inicio" id="f-inicio" value="{{ old('inicio') }}" required></div>
                    <div class="campo"><label>Dias de gozo</label><input type="number" name="dias" id="f-dias" min="5" max="30" value="{{ old('dias', 30) }}" required></div>
                    <div class="campo"><label>Vender dias (abono 1/3)</label><input type="number" name="dias_abono" id="f-abono" min="0" max="10" value="{{ old('dias_abono', 0) }}"></div>
                </div>
                <div class="campo" style="margin-top:10px"><label>Observação</label><input name="observacao" value="{{ old('observacao') }}" maxlength="1000"></div>
                <p style="display:flex;gap:16px;flex-wrap:wrap;margin:12px 0">
                    <label style="margin:0"><input type="checkbox" name="adiantamento_13" value="1" @checked(old('adiantamento_13'))> Receber adiantamento da 1ª parcela do 13º</label>
                    @if($u->podeAprovarPara($colaborador))<label style="margin:0"><input type="checkbox" name="aprovar_direto" value="1"> Já aprovar (lançamento do RH/DP)</label>@endif
                </p>
                <div id="f-resultado" class="muted"></div>
                <p><button class="btn">{{ $colaborador->user_id === $u->id ? 'Enviar para aprovação' : 'Lançar férias' }}</button></p>
                <p class="muted">Regras: até 3 períodos, um com pelo menos 14 dias e os demais com pelo menos 5; venda de até 1/3; não pode começar nos 2 dias antes de feriado ou domingo.</p>
            </form>
        </div>
    @endif

    @foreach($periodos as $p)
        @php($a = $alertas->get($p->id))
        <div class="card">
            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                <h2>Aquisitivo {{ $p->rotulo }}
                    @if($p->perdido)<span class="selo erro">Direito perdido</span>
                    @elseif(! $p->completo())<span class="selo">Em formação</span>
                    @elseif($p->saldo() === 0)<span class="selo ok">Programado</span>@endif
                    @if($a)<span class="selo {{ \App\Services\Ferias::NIVEIS[$a->nivel][1] }}">{{ \App\Services\Ferias::NIVEIS[$a->nivel][0] }}</span>@endif
                </h2>
                <div class="muted">Concessivo até <strong>{{ $p->limite_concessivo->format('d/m/Y') }}</strong> · direito {{ $p->dias_direito }} dias · saldo {{ $p->saldo() }}
                    @if($p->faltas_injustificadas) · {{ $p->faltas_injustificadas }} faltas @endif</div>
            </div>
            @if($a)<p class="muted" style="margin-top:0">Para não perder o prazo, os {{ $a->saldo }} dias restantes precisam começar até {{ $a->ultimo_inicio->format('d/m/Y') }}{{ $a->faltam < 0 ? ' (prazo já passou: pagamento em dobro)' : " (faltam {$a->faltam} dias)" }}.</p>@endif
            @if($p->programacoes->isNotEmpty())
                <div class="tabela"><table>
                    <tr><th>Gozo</th><th class="num">Dias</th><th class="num">Venda</th><th>13º</th><th>Situação</th><th></th></tr>
                    @foreach($p->programacoes as $f)
                        <tr @class(['fds' => in_array($f->status, ['cancelada', 'rejeitada'])])>
                            <td>{{ $f->inicio->format('d/m/Y') }} a {{ $f->fim->format('d/m/Y') }}<div class="muted">retorno {{ $f->fim->copy()->addDay()->format('d/m/Y') }}</div></td>
                            <td class="num">{{ $f->dias }}</td><td class="num">{{ $f->dias_abono ?: '' }}</td><td>{{ $f->adiantamento_13 ? 'Sim' : '' }}</td>
                            <td><span class="selo {{ $f->selo }}">{{ $f->situacao }}</span>
                                @if($f->resposta)<div class="muted">“{{ $f->resposta }}”</div>@endif</td>
                            <td class="num">@if($f->status === 'pendente' || ($f->status === 'aprovada' && $u->ehGestaoPessoal() && $f->inicio->gt(today())))
                                <form method="post" action="{{ route('ferias.cancelar', $f) }}" onsubmit="return confirm('Cancelar esta programação?')">@csrf<button class="btn sec peq">Cancelar</button></form>
                            @endif</td>
                        </tr>
                    @endforeach
                </table></div>
            @endif
            @if($u->ehGestaoPessoal())
                <details style="margin-top:10px"><summary>Faltas injustificadas e perda do direito</summary>
                    <form method="post" action="{{ route('ferias.periodo', $p) }}" class="filtros" style="margin-top:10px">
                        @csrf @method('put')
                        <div><label>Faltas injustificadas no período</label><input type="number" name="faltas_injustificadas" min="0" value="{{ $p->faltas_injustificadas }}"></div>
                        <div><label style="margin-top:24px"><input type="checkbox" name="perdido" value="1" @checked($p->perdido)> Perdeu o direito (art. 133)</label></div>
                        <div style="flex:1"><label>Observação</label><input name="observacao" value="{{ $p->observacao }}"></div>
                        <div><button class="btn sec">Salvar</button></div>
                    </form>
                    <p class="muted">Até 5 faltas: 30 dias · 6 a 14: 24 · 15 a 23: 18 · 24 a 32: 12 · acima de 32: sem direito (art. 130).</p>
                </details>
            @endif
        </div>
    @endforeach
@endif
@endsection
@push('scripts')
<script>
(function () {
    const f = document.getElementById('form-ferias');
    if (!f) return;
    const out = document.getElementById('f-resultado');
    let t;
    function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function simular() {
        clearTimeout(t);
        t = setTimeout(async () => {
            const p = new URLSearchParams({periodo_id: f.periodo_id.value, inicio: f.inicio.value, dias: f.dias.value || 0, dias_abono: f.dias_abono.value || 0});
            if (!f.inicio.value || !f.dias.value) { out.innerHTML = ''; return; }
            const r = await fetch('{{ route('ferias.simular') }}?' + p, {headers: {'Accept': 'application/json'}});
            if (!r.ok) { out.innerHTML = ''; return; }
            const j = await r.json();
            let h = '<p>Gozo até <strong>' + j.fim + '</strong>, retorno em <strong>' + j.retorno + '</strong>.</p>';
            if (j.erros.length) h += '<ul class="avisos erros">' + j.erros.map(e => '<li>' + esc(e) + '</li>').join('') + '</ul>';
            if (j.avisos.length) h += '<ul class="avisos alertas">' + j.avisos.map(e => '<li>' + esc(e) + '</li>').join('') + '</ul>';
            out.innerHTML = h;
        }, 300);
    }
    ['change', 'input'].forEach(ev => f.addEventListener(ev, simular));
    simular();
})();
</script>
@endpush
