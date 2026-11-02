@php($lido = $c->relationLoaded('leituras') ? $c->leituras->first() : null)
<div @class(['comunicado', 'urgente' => $c->categoria === 'urgente', 'nao-lido' => ! $lido])>
    <h3><a href="{{ route('mural.show', $c) }}">@if($c->fixado)📌 @endif{{ $c->titulo }}</a>
        @if(! $lido)<span class="selo alerta">Novo</span>@endif
        @if($c->exige_ciencia && ! $lido?->ciente_em)<span class="selo erro">Requer ciência</span>@endif</h3>
    <div class="muted">{{ \App\Models\Comunicado::CATEGORIAS[$c->categoria] ?? $c->categoria }} · {{ $c->publicar_em->format('d/m/Y H:i') }} · {{ $c->autor?->name }} · para {{ $c->publico_texto }}</div>
    <div class="corpo" style="margin-top:8px">{{ \Illuminate\Support\Str::limit($c->corpo, 280) }}</div>
</div>
