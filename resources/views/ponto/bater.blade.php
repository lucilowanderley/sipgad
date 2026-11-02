@extends('layouts.app')
@section('titulo', 'Registrar ponto')
@section('conteudo')
@if(! $colaborador)
    <div class="card">Seu usuário não está vinculado a um colaborador.</div>
@else
<div class="card" style="text-align:center">
    <div class="muted">{{ now()->translatedFormat('l, d \d\e F \d\e Y') }}</div>
    <div class="relogio" id="relogio">{{ now()->format('H:i:s') }}</div>
    <div class="muted">{{ $colaborador->nome }} · matrícula {{ $colaborador->matricula }}</div>
    <form method="post" action="{{ route('ponto.registrar') }}" onsubmit="this.querySelector('button').disabled=true">
        @csrf
        <button class="btn botao-ponto">Registrar ponto</button>
    </form>
    <p class="muted">A hora registrada é a do servidor, no momento do clique.</p>
</div>
<div class="card">
    <h2>Registros de hoje</h2>
    @forelse($hoje as $m)
        <span class="selo">{{ $m->data_hora->format('H:i') }}</span>
        <a class="muted" href="{{ route('ponto.comprovante', $m) }}">comprovante</a> &nbsp;
    @empty
        <span class="muted">Nenhum registro hoje.</span>
    @endforelse
</div>
@push('scripts')
<script>
// Relógio sincronizado com o servidor (corrige a diferença do relógio do computador)
const desvio = {{ now()->getTimestampMs() }} - Date.now();
setInterval(() => {
    const d = new Date(Date.now() + desvio);
    document.getElementById('relogio').textContent = d.toLocaleTimeString('pt-BR', {timeZone: '{{ config('app.timezone') }}'});
}, 500);
</script>
@endpush
@endif
@endsection
