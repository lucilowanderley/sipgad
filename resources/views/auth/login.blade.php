<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · SIPGAD</title>
    <link rel="stylesheet" href="{{ asset('css/sipgad.css') }}">
</head>
<body>
<div class="login">
    <h1 style="text-align:center;color:var(--primaria);margin-bottom:4px">SIPGAD</h1>
    <p class="muted" style="text-align:center;margin-top:0">Sistema Integrado de Ponto eletrônico e Gestão Administrativa</p>
    <form method="post" action="{{ route('login') }}" class="card">
        @csrf
        <div class="campo">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email')<div class="erro-campo">{{ $message }}</div>@enderror
        </div>
        <div class="campo" style="margin-top:12px">
            <label for="password">Senha</label>
            <input id="password" type="password" name="password" required>
        </div>
        <label style="margin:12px 0"><input type="checkbox" name="lembrar" value="1"> Manter conectado</label>
        <button class="btn" style="width:100%">Entrar</button>
    </form>
</div>
</body>
</html>
