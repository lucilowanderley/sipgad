@extends('layouts.app')
@section('titulo', $relogio->nome)
@section('conteudo')
<div class="grade">
    <div class="kpi"><span>Equipamento</span><strong style="font-size:16px">{{ $relogio->fabricante }} {{ $relogio->modelo }}</strong><div class="muted">REP-{{ $relogio->tipo_rep }} · nº {{ $relogio->numero_fabricacao ?: 'não informado' }}</div></div>
    <div class="kpi"><span>Último NSR importado</span><strong>{{ $relogio->ultimo_nsr }}</strong></div>
    <div class="kpi"><span>Última coleta</span><strong style="font-size:18px">{{ $relogio->ultima_coleta_em?->format('d/m/Y H:i') ?? 'nunca' }}</strong></div>
    <div class="kpi"><span>Rede</span><strong style="font-size:18px">{{ $relogio->ip ? $relogio->ip.':'.($relogio->porta ?: 80) : 'sem IP' }}</strong>
        <div style="margin-top:6px">
            <a class="btn sec peq" href="{{ route('relogios.edit', $relogio) }}">Editar</a>
            @if($relogio->ip)
                <form class="inline" method="post" action="{{ route('relogios.testar', $relogio) }}">@csrf<button class="btn sec peq">Testar conexão</button></form>
                <a class="btn sec peq" href="http://{{ $relogio->ip }}{{ $relogio->porta && $relogio->porta != 80 ? ':'.$relogio->porta : '' }}" target="_blank" rel="noopener">Abrir página do relógio</a>
            @endif
        </div>
    </div>
</div>

<div class="card">
    <h2>Importar AFD</h2>
    <p class="muted">No relógio Dimep, coloque um pendrive na porta USB e use <strong>F1 + 80 + E</strong> (ou a porta <strong>FISCAL</strong>) para gravar o AFD. Depois envie o arquivo .txt aqui. Marcações já importadas são ignoradas, então pode enviar o arquivo completo sempre.</p>
    <form method="post" action="{{ route('relogios.importar', $relogio) }}" enctype="multipart/form-data" class="filtros">
        @csrf
        <div style="min-width:300px"><label>Arquivo AFD</label><input type="file" name="arquivo" accept=".txt,.afd,text/plain" required>@error('arquivo')<div class="erro-campo">{{ $message }}</div>@enderror</div>
        <div><button class="btn">Importar</button></div>
    </form>
</div>

<h2 style="font-size:16px">Importações</h2>
<div class="tabela"><table>
    <tr><th>Quando</th><th>Origem</th><th>Arquivo</th><th>Leiaute</th><th>NSR</th><th class="num">No arquivo</th><th class="num">Importadas</th><th class="num">Já existiam</th><th class="num">Pendentes</th><th></th></tr>
    @forelse($importacoes as $i)
        <tr>
            <td>{{ $i->created_at->format('d/m/Y H:i') }}<div class="muted">{{ $i->user?->name ?? 'automática' }}</div></td>
            <td>{{ ['upload' => 'Envio manual', 'pasta' => 'Pasta automática', 'reprocessamento' => 'Reprocessamento'][$i->origem] ?? $i->origem }}</td>
            <td><a href="{{ route('relogios.arquivo', $i) }}">{{ $i->arquivo_nome }}</a></td>
            <td>{{ $i->leiaute ? 'Port. '.$i->leiaute : '—' }}</td>
            <td>{{ $i->nsr_inicial }}–{{ $i->nsr_final }}</td>
            <td class="num">{{ $i->marcacoes_arquivo }}</td>
            <td class="num">{{ $i->importadas }}</td>
            <td class="num">{{ $i->duplicadas }}</td>
            <td class="num">@if($i->pendentes)<span class="selo alerta">{{ $i->pendentes }}</span>@else 0 @endif</td>
            <td class="num">@if($i->pendentes)<form class="inline" method="post" action="{{ route('relogios.reprocessar', $i) }}">@csrf<button class="btn sec peq" title="Importa de novo as pendentes, depois de corrigir o cadastro">Reprocessar</button></form>@endif</td>
        </tr>
        @if($i->pendencias)
            <tr><td colspan="10" style="background:#fffbeb">
                <details @if($loop->first) open @endif><summary class="muted">{{ count($i->pendencias) }} pendência(s)/aviso(s)</summary>
                <ul class="avisos alertas">
                    @foreach(array_slice($i->pendencias, 0, 200) as $p)
                        <li>@if($p['nsr'])NSR {{ $p['nsr'] }} · {{ $p['data_hora'] }} · {{ $p['identificador'] }} — @endif{{ $p['motivo'] }}</li>
                    @endforeach
                    @if(count($i->pendencias) > 200)<li>… e mais {{ count($i->pendencias) - 200 }}.</li>@endif
                </ul></details>
            </td></tr>
        @endif
    @empty
        <tr><td colspan="10" class="muted">Nenhum AFD importado ainda.</td></tr>
    @endforelse
</table></div>
<div class="paginacao">{{ $importacoes->links() }}</div>

<h2 style="font-size:16px;margin-top:20px">Últimas marcações deste relógio</h2>
<div class="tabela"><table>
    <tr><th>Data e hora</th><th>Colaborador</th><th>NSR no relógio</th><th>NSR no SIPGAD</th></tr>
    @forelse($ultimas as $m)
        <tr @class(['marcacao-desc' => $m->desconsiderada_em])><td>{{ $m->data_hora->format('d/m/Y H:i') }}</td><td>{{ $m->colaborador->nome }}</td><td>{{ $m->nsr_relogio }}</td><td>{{ $m->nsr }}</td></tr>
    @empty
        <tr><td colspan="4" class="muted">Nenhuma marcação importada.</td></tr>
    @endforelse
</table></div>
@endsection
