@extends('layouts.app')
@section('titulo', 'Auditoria')
@section('conteudo')
<form class="filtros" method="get">
    <div><label>Entidade</label><select name="entidade" onchange="this.form.submit()"><option value="">Todas</option>
        @foreach($entidades as $e)<option @selected(request('entidade') === $e)>{{ $e }}</option>@endforeach</select></div>
    <div><a class="btn sec" href="{{ route('auditoria', ['verificar' => 1]) }}">Verificar integridade das marcações</a></div>
</form>
@if($integridade)
    <div class="card"><h2>Integridade da cadeia de marcações</h2>
        @foreach($integridade as $empresa => $nsr)
            <div>{{ $empresa }}: @if($nsr === null)<span class="selo ok">Íntegra</span>@else<span class="selo erro">Adulteração detectada a partir do NSR {{ $nsr }}</span>@endif</div>
        @endforeach
    </div>
@endif
<div class="tabela"><table>
    <tr><th>Quando</th><th>Usuário</th><th>Ação</th><th>Registro</th><th>Antes</th><th>Depois</th><th>IP</th></tr>
    @foreach($registros as $r)
        <tr><td style="white-space:nowrap">{{ $r->created_at?->format('d/m/Y H:i:s') }}</td><td>{{ $r->user?->name ?? 'sistema' }}</td><td>{{ $r->acao }}</td>
            <td>{{ $r->entidade }} #{{ $r->entidade_id }}</td>
            <td class="muted" style="font-size:12px;max-width:260px;word-break:break-word">{{ $r->antes ? json_encode($r->antes, JSON_UNESCAPED_UNICODE) : '' }}</td>
            <td class="muted" style="font-size:12px;max-width:260px;word-break:break-word">{{ $r->depois ? json_encode($r->depois, JSON_UNESCAPED_UNICODE) : '' }}</td>
            <td>{{ $r->ip }}</td></tr>
    @endforeach
</table></div>
<div class="paginacao">{{ $registros->links() }}</div>
@endsection
