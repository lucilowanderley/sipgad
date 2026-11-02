@extends('layouts.app')
@section('titulo', 'Comprovante de registro de ponto')
@section('conteudo')
@php($c = $marcacao->colaborador)
<div class="card" style="max-width:520px;font-family:ui-monospace,Consolas,monospace">
    <h2 style="font-family:inherit">COMPROVANTE DE REGISTRO DE PONTO DO TRABALHADOR</h2>
    <p>{{ $c->empresa->razao_social }}<br>CNPJ {{ $c->empresa->cnpj }}<br>{{ $c->empresa->endereco }} {{ $c->empresa->cidade }}/{{ $c->empresa->uf }}</p>
    <p>Trabalhador: {{ $c->nome }}<br>CPF: {{ $c->cpf }}</p>
    <p><strong style="font-size:20px">{{ $marcacao->data_hora->format('d/m/Y H:i') }}</strong><br>
       NSR: {{ str_pad($marcacao->nsr, 9, '0', STR_PAD_LEFT) }}<br>
       Origem: {{ $marcacao->origem === 'ajuste' ? 'Inclusão manual pelo empregador' : 'Registro web (REP-P)' }}</p>
    <p style="word-break:break-all;font-size:12px">Código de verificação:<br>{{ $marcacao->hash }}</p>
    <p class="nao-imprimir"><button class="btn sec" onclick="window.print()">Imprimir</button> <a class="btn sec" href="{{ route('ponto.bater') }}">Voltar</a></p>
</div>
@endsection
