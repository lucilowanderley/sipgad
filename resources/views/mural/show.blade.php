@extends('layouts.app')
@section('titulo', 'Comunicado')
@section('conteudo')
<p><a href="{{ route('mural.index') }}">← Voltar ao mural</a></p>
<div @class(['comunicado', 'urgente' => $comunicado->categoria === 'urgente'])>
    <h3 style="font-size:20px">@if($comunicado->fixado)📌 @endif{{ $comunicado->titulo }}</h3>
    <div class="muted">{{ \App\Models\Comunicado::CATEGORIAS[$comunicado->categoria] ?? '' }} · publicado em {{ $comunicado->publicar_em->format('d/m/Y H:i') }} por {{ $comunicado->autor?->name }} · para {{ $comunicado->publico_texto }}
        @if($comunicado->expira_em) · disponível até {{ $comunicado->expira_em->format('d/m/Y') }}@endif</div>
    <div class="corpo" style="margin-top:14px">{{ $comunicado->corpo }}</div>
    @if($comunicado->exige_ciencia)
        <div style="margin-top:16px">
            @if($leitura->ciente_em)
                <span class="selo ok">Ciência registrada em {{ $leitura->ciente_em->format('d/m/Y H:i') }}</span>
            @elseif(\App\Models\Comunicado::visiveisPara(auth()->user())->whereKey($comunicado->id)->exists())
                <form method="post" action="{{ route('mural.ciencia', $comunicado) }}">@csrf<button class="btn">Li e estou ciente</button></form>
            @endif
        </div>
    @endif
</div>
@if(auth()->user()->ehGestaoPessoal())
    <p style="margin-top:16px;display:flex;gap:8px">
        <a class="btn sec" href="{{ route('mural.leituras', $comunicado) }}">Quem leu</a>
        <a class="btn sec" href="{{ route('mural.edit', $comunicado) }}">Editar</a>
        <form class="inline" method="post" action="{{ route('mural.destroy', $comunicado) }}" onsubmit="return confirm('Excluir este comunicado?')">@csrf @method('delete')<button class="btn perigo">Excluir</button></form>
    </p>
@endif
@endsection
