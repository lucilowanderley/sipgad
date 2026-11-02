<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Painel') · SIPGAD</title>
    <link rel="stylesheet" href="{{ asset('css/sipgad.css') }}">
</head>
<body>
@php($u = auth()->user())
<div class="app">
    <nav class="lateral">
        <div class="marca">SIPGAD<small>Ponto e Gestão Administrativa</small></div>
        <a href="{{ route('painel') }}" @class(['ativo' => request()->routeIs('painel')])>Painel</a>
        <a href="{{ route('mural.index') }}" @class(['ativo' => request()->routeIs('mural.*')])>Mural de comunicados @if($menuNaoLidos ?? 0)<span class="contador">{{ $menuNaoLidos }}</span>@endif</a>
        @if($u->aprova())
            <a href="{{ route('aprovacoes.index') }}" @class(['ativo' => request()->routeIs('aprovacoes.*')])>Aprovações @if($menuPendentes ?? 0)<span class="contador">{{ $menuPendentes }}</span>@endif</a>
        @endif

        <h6>Ponto</h6>
        @if($u->colaborador)
            <a href="{{ route('ponto.bater') }}" @class(['ativo' => request()->routeIs('ponto.*')])>Registrar ponto</a>
        @endif
        <a href="{{ route('espelho') }}" @class(['ativo' => request()->routeIs('espelho')])>Espelho de ponto</a>
        <a href="{{ route('banco-horas.index') }}" @class(['ativo' => request()->routeIs('banco-horas.*')])>Banco de horas</a>
        @if($u->temPerfil('admin', 'dp'))
            <a href="{{ route('relogios.index') }}" @class(['ativo' => request()->routeIs('relogios.*')])>Relógios de ponto</a>
            <a href="{{ route('fechamentos.index') }}" @class(['ativo' => request()->routeIs('fechamentos.*')])>Fechamento</a>
        @endif

        <h6>Portal</h6>
        @if($u->colaborador || $u->ehGestaoPessoal())
            <a href="{{ route('solicitacoes.index') }}" @class(['ativo' => request()->routeIs('solicitacoes.*')])>Solicitações</a>
        @endif
        <a href="{{ route('ferias.index') }}" @class(['ativo' => request()->routeIs('ferias.index')])>Férias</a>
        <a href="{{ route('ferias.cronograma') }}" @class(['ativo' => request()->routeIs('ferias.cronograma')])>Cronograma de férias</a>
        @if($u->aprova())
            <a href="{{ route('ferias.controle') }}" @class(['ativo' => request()->routeIs('ferias.controle')])>Controle de férias</a>
        @endif
        @if($u->colaborador)
            <a href="{{ route('documentos.index') }}" @class(['ativo' => request()->routeIs('documentos.index') && ! request('colaborador_id')])>Meus documentos</a>
        @endif
        @if($u->ehGestaoPessoal())
            <a href="{{ route('documentos.vencimentos') }}" @class(['ativo' => request()->routeIs('documentos.vencimentos') || (request()->routeIs('documentos.index') && request('colaborador_id'))])>Dossiês e vencimentos</a>
        @endif

        @if($u->ehGestaoPessoal())
            <h6>Cadastros</h6>
            <a href="{{ route('colaboradores.index') }}" @class(['ativo' => request()->routeIs('colaboradores.*')])>Colaboradores</a>
            <a href="{{ route('jornadas.index') }}" @class(['ativo' => request()->routeIs('jornadas.*')])>Jornadas e escalas</a>
            <a href="{{ route('setores.index') }}" @class(['ativo' => request()->routeIs('setores.*')])>Setores</a>
            <a href="{{ route('cargos.index') }}" @class(['ativo' => request()->routeIs('cargos.*')])>Cargos</a>
            <a href="{{ route('feriados.index') }}" @class(['ativo' => request()->routeIs('feriados.*')])>Feriados</a>
            <a href="{{ route('tipos-documento.index') }}" @class(['ativo' => request()->routeIs('tipos-documento.*')])>Tipos de documento</a>
            <a href="{{ route('empresas.index') }}" @class(['ativo' => request()->routeIs('empresas.*')])>Empresas</a>
        @endif
        @if($u->temPerfil('admin'))
            <h6>Sistema</h6>
            <a href="{{ route('auditoria') }}" @class(['ativo' => request()->routeIs('auditoria')])>Auditoria</a>
        @endif
    </nav>
    <main class="conteudo">
        <div class="topo">
            <h1>@yield('titulo', 'Painel')</h1>
            <div class="usuario">
                {{ $u->name }} · {{ \App\Models\User::PERFIS[$u->perfil] ?? $u->perfil }}
                <form method="post" action="{{ route('logout') }}" class="inline">@csrf<button class="btn sec peq">Sair</button></form>
            </div>
        </div>
        @if(session('ok'))<div class="alerta ok">{{ session('ok') }}</div>@endif
        @if(session('erro'))<div class="alerta erro">{{ session('erro') }}</div>@endif
        @if($errors->any())<div class="alerta erro">Verifique os campos destacados.</div>@endif
        @yield('conteudo')
    </main>
</div>
@stack('scripts')
</body>
</html>
