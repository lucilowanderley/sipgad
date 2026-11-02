@php($h = fn ($m, $s = false) => \App\Services\Apuracao::hhmm($m, $s))
@php($dp = ($podeAjustar ?? false))
<div class="tabela"><table>
    <tr><th>Data</th><th>Previsto</th><th>Marcações</th><th class="num">Trabalhado</th><th class="num">Extras</th><th class="num">Débito</th><th class="num">Abonado</th><th class="num">Noturno</th><th>Ocorrência</th></tr>
    @foreach($resultado['dias'] as $d)
        @php($fds = ($d['futuro'] ?? false) || ($d['previsto'] === 0 && ! $d['marcacoes']->count()))
        <tr @class(['fds' => $fds])>
            <td style="white-space:nowrap">{{ $d['data']->format('d/m') }} {{ mb_substr(\App\Models\Jornada::DIAS[$d['data']->dayOfWeek], 0, 3) }}</td>
            <td class="muted" style="white-space:nowrap">{{ collect($d['previstos'])->map(fn ($p) => $p[0]->format('H:i').'-'.$p[1]->format('H:i'))->implode(' ') }}</td>
            <td>
                @foreach($d['marcacoes'] as $m)
                    <span @class(['marcacao-ajuste' => $m->origem === 'ajuste']) title="NSR {{ $m->nsr }}{{ $m->motivo ? ' · '.$m->motivo : '' }}">{{ $m->data_hora->format('H:i') }}{{ $m->origem === 'ajuste' ? '*' : '' }}</span>@if($dp)<form class="inline nao-imprimir" method="post" action="{{ route('ajustes.desconsiderar', $m) }}" onsubmit="const r=prompt('Motivo para desconsiderar a marcação das {{ $m->data_hora->format('H:i') }}:');if(!r)return false;this.motivo.value=r;"><input type="hidden" name="motivo">@csrf<button class="btn sec peq" title="Desconsiderar" style="padding:0 5px;margin:0 6px 0 2px">×</button></form>@else&nbsp;@endif
                @endforeach
                @foreach(($desconsideradas[$d['data']->toDateString()] ?? []) as $m)
                    <span class="marcacao-desc nao-imprimir" title="Desconsiderada: {{ $m->motivo_desconsideracao }}">{{ $m->data_hora->format('H:i') }}</span>
                @endforeach
            </td>
            <td class="num">{{ $d['trabalhado'] ? $h($d['trabalhado']) : '' }}</td>
            <td class="num pos">{{ $d['extras'] ? $h($d['extras']) : '' }}</td>
            <td class="num neg">{{ $d['debito'] ? $h($d['debito']) : '' }}</td>
            <td class="num">{{ ($d['abonado'] ?? 0) ? $h($d['abonado']) : '' }}</td>
            <td class="num">{{ $d['noturno'] ? $h($d['noturno']) : '' }}</td>
            <td>@if($d['falta'] || $d['incompleto'])<span class="selo erro">{{ $d['ocorrencia'] }}</span>@elseif($d['ferias'] ?? false)<span class="selo ok">Férias</span>@elseif(($d['abonado'] ?? 0) || str_starts_with((string) $d['ocorrencia'], 'Abono') || str_starts_with((string) $d['ocorrencia'], 'Atestado'))<span class="selo">{{ $d['ocorrencia'] }}</span>@elseif($d['ocorrencia'])<span class="muted">{{ $d['ocorrencia'] }}</span>@endif</td>
        </tr>
    @endforeach
    @php($t = $resultado['totais'])
    <tr style="font-weight:700"><td colspan="3">Totais · previsto {{ $h($t['previsto']) }}</td>
        <td class="num">{{ $h($t['trabalhado']) }}</td><td class="num pos">{{ $h($t['extras']) }}</td><td class="num neg">{{ $h($t['debito']) }}</td><td class="num">{{ $h($t['abonado'] ?? 0) }}</td>
        <td class="num">{{ $h($t['noturno']) }}</td><td>Saldo {{ $h($t['saldo'], true) }}</td></tr>
</table></div>
<p class="muted">* marcação incluída pelo DP. Noturno em hora real; em hora reduzida: {{ $h(\App\Services\Apuracao::horaNoturnaReduzida($t['noturno'])) }}. Faltas: {{ $t['faltas'] }}.@if($t['dias_ferias'] ?? 0) Dias de férias: {{ $t['dias_ferias'] }}.@endif Abonado = horas justificadas por abono ou atestado aprovado.</p>
