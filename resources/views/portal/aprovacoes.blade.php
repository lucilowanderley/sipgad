@extends('layouts.app')
@section('titulo', 'Aprovações')
@section('conteudo')
<p class="muted">{{ auth()->user()->ehGestaoPessoal() ? 'Você vê os pedidos de todos os colaboradores. Os gestores decidem os da própria equipe; o RH/DP pode decidir quando o gestor não estiver disponível.' : 'Pedidos da sua equipe direta aguardando sua decisão.' }}</p>

<div class="card">
    <h2>Solicitações de ponto ({{ $solicitacoes->count() }})</h2>
    @if($solicitacoes->isEmpty())
        <p class="muted">Nenhuma solicitação pendente.</p>
    @else
    <div class="tabela"><table>
        <tr><th>Colaborador</th><th>Tipo</th><th>Período</th><th>Motivo</th><th>Decisão</th></tr>
        @foreach($solicitacoes as $s)
            <tr>
                <td><strong>{{ $s->colaborador->nome }}</strong><div class="muted">{{ $s->colaborador->setor?->nome }} · enviada {{ $s->created_at->format('d/m H:i') }}
                    @if($s->autor && $s->autor->id !== $s->colaborador->user_id) por {{ $s->autor->name }}@endif</div>
                    @if(auth()->user()->ehGestaoPessoal())<div class="muted">Gestor: {{ $s->colaborador->gestor?->nome ?? '— (RH/DP)' }}</div>@endif</td>
                <td>{{ \App\Models\Solicitacao::TIPOS[$s->tipo] }}@if($s->cid)<div class="muted">CID {{ $s->cid }}</div>@endif</td>
                <td>{{ $s->periodo_texto }}<div><a href="{{ route('espelho', ['colaborador_id' => $s->colaborador_id, 'competencia' => $s->data->format('Y-m')]) }}" target="_blank">ver espelho</a></div></td>
                <td>{{ $s->motivo }}@if($s->anexo_path)<div><a href="{{ route('solicitacoes.anexo', $s) }}" target="_blank">📎 {{ $s->anexo_nome }}</a></div>@endif</td>
                <td>@include('portal._decisao', ['acao' => route('aprovacoes.solicitacao', $s)])</td>
            </tr>
        @endforeach
    </table></div>
    @endif
</div>

<div class="card">
    <h2>Férias ({{ $programacoes->count() }})</h2>
    @if($programacoes->isEmpty())
        <p class="muted">Nenhuma programação de férias pendente.</p>
    @else
    <div class="tabela"><table>
        <tr><th>Colaborador</th><th>Período de gozo</th><th>Aquisitivo</th><th>Observações e avisos</th><th>Decisão</th></tr>
        @foreach($programacoes as $p)
            @php($v = $avisosFerias[$p->id])
            <tr>
                <td><strong>{{ $p->colaborador->nome }}</strong><div class="muted">{{ $p->colaborador->setor?->nome }}</div></td>
                <td style="white-space:nowrap">{{ $p->inicio->format('d/m/Y') }} a {{ $p->fim->format('d/m/Y') }}<div class="muted">{{ $p->dias }} dias
                    @if($p->dias_abono) + venda de {{ $p->dias_abono }}@endif @if($p->adiantamento_13) · adiant. 13º@endif</div></td>
                <td>{{ $p->periodo->rotulo }}<div class="muted">vence {{ $p->periodo->limite_concessivo->format('d/m/Y') }}</div></td>
                <td>@if($p->observacao)<div>{{ $p->observacao }}</div>@endif
                    @if($v['erros'])<ul class="avisos erros">@foreach($v['erros'] as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
                    @if($v['avisos'])<ul class="avisos alertas">@foreach($v['avisos'] as $a)<li>{{ $a }}</li>@endforeach</ul>@endif
                    <a href="{{ route('ferias.cronograma', ['setor_id' => $p->colaborador->setor_id, 'ano' => $p->inicio->year]) }}" target="_blank">cronograma do setor</a></td>
                <td>@include('portal._decisao', ['acao' => route('aprovacoes.ferias', $p)])</td>
            </tr>
        @endforeach
    </table></div>
    @endif
</div>

@if($historico->isNotEmpty())
    <details class="card"><summary>Decididas nos últimos 30 dias ({{ $historico->count() }})</summary>
        <div class="tabela" style="margin-top:10px"><table>
            <tr><th>Colaborador</th><th>Tipo</th><th>Período</th><th>Situação</th><th>Por</th></tr>
            @foreach($historico as $s)
                <tr><td>{{ $s->colaborador->nome }}</td><td>{{ \App\Models\Solicitacao::TIPOS[$s->tipo] }}</td><td>{{ $s->periodo_texto }}</td>
                    <td>@include('portal._status', ['status' => $s->status])</td><td>{{ $s->decisor?->name }} · {{ $s->decidido_em->format('d/m H:i') }}</td></tr>
            @endforeach
        </table></div>
    </details>
@endif
@endsection
