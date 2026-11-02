<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Espelho de ponto {{ $inicio->format('m/Y') }} · {{ $colaborador?->nome }}</title>
    <link rel="stylesheet" href="{{ asset('css/sipgad.css') }}">
    <style>body{background:#fff;font-size:12px;padding:20px}th,td{padding:4px 6px}.assinaturas{display:flex;gap:60px;margin-top:50px}.assinaturas div{flex:1;border-top:1px solid #000;text-align:center;padding-top:4px}</style>
</head>
<body onload="window.print()">
@if($colaborador)
    <h2 style="margin:0">Espelho de ponto · {{ $inicio->format('m/Y') }}</h2>
    <p>{{ $colaborador->empresa->razao_social }} · CNPJ {{ $colaborador->empresa->cnpj }}<br>
       {{ $colaborador->nome }} · CPF {{ $colaborador->cpf }} · Matrícula {{ $colaborador->matricula }} · {{ $colaborador->cargo?->nome }} · Admissão {{ $colaborador->data_admissao->format('d/m/Y') }}<br>
       Período {{ $inicio->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }} · {{ $fechado ? 'Competência fechada' : 'Competência aberta (prévia)' }}</p>
    @include('ponto._tabela-espelho', ['podeAjustar' => false, 'desconsideradas' => collect()])
    <div class="assinaturas"><div>{{ $colaborador->nome }}</div><div>{{ $colaborador->empresa->razao_social }}</div></div>
@endif
</body>
</html>
