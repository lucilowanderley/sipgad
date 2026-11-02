@extends('layouts.app')
@section('titulo', $colaborador->exists ? 'Editar colaborador' : 'Novo colaborador')
@section('conteudo')
@php($v = fn ($c) => old($c, $colaborador->$c instanceof \Carbon\CarbonInterface ? $colaborador->$c->format('Y-m-d') : $colaborador->$c))
@php($jAtual = $colaborador->exists ? $colaborador->jornadaAtual() : null)
@php($err = fn ($c) => $errors->first($c))
<form method="post" action="{{ $colaborador->exists ? route('colaboradores.update', $colaborador) : route('colaboradores.store') }}">
    @csrf
    @if($colaborador->exists) @method('put') @endif

    <div class="card"><h2>Dados pessoais</h2><div class="campos">
        <div class="campo"><label>Nome completo</label><input name="nome" value="{{ $v('nome') }}" required><div class="erro-campo">{{ $err('nome') }}</div></div>
        <div class="campo"><label>CPF</label><input name="cpf" value="{{ $v('cpf') }}" placeholder="000.000.000-00" required><div class="erro-campo">{{ $err('cpf') }}</div></div>
        <div class="campo"><label>PIS/NIS</label><input name="pis" value="{{ $v('pis') }}"></div>
        <div class="campo"><label>Data de nascimento</label><input type="date" name="data_nascimento" value="{{ $v('data_nascimento') }}"></div>
        <div class="campo"><label>E-mail pessoal</label><input type="email" name="email" value="{{ $v('email') }}"><div class="erro-campo">{{ $err('email') }}</div></div>
        <div class="campo"><label>Telefone</label><input name="telefone" value="{{ $v('telefone') }}"></div>
    </div></div>

    <div class="card"><h2>Contrato</h2><div class="campos">
        <div class="campo"><label>Empresa</label><select name="empresa_id">@foreach($empresas as $e)<option value="{{ $e->id }}" @selected($v('empresa_id') == $e->id)>{{ $e->nome }}</option>@endforeach</select></div>
        <div class="campo"><label>Matrícula</label><input name="matricula" value="{{ $v('matricula') }}" required><div class="erro-campo">{{ $err('matricula') }}</div></div>
        <div class="campo"><label>Setor</label><select name="setor_id"><option value="">—</option>@foreach($setores as $s)<option value="{{ $s->id }}" @selected($v('setor_id') == $s->id)>{{ $s->nome }}</option>@endforeach</select></div>
        <div class="campo"><label>Cargo</label><select name="cargo_id"><option value="">—</option>@foreach($cargos as $c)<option value="{{ $c->id }}" @selected($v('cargo_id') == $c->id)>{{ $c->nome }}</option>@endforeach</select></div>
        <div class="campo"><label>Gestor imediato</label><select name="gestor_id"><option value="">—</option>@foreach($gestores as $g)<option value="{{ $g->id }}" @selected($v('gestor_id') == $g->id)>{{ $g->nome }}</option>@endforeach</select></div>
        <div class="campo"><label>Tipo de contrato</label><select name="tipo_contrato">@foreach(\App\Models\Colaborador::TIPOS_CONTRATO as $k => $n)<option value="{{ $k }}" @selected($v('tipo_contrato') === $k)>{{ $n }}</option>@endforeach</select></div>
        <div class="campo"><label>Admissão</label><input type="date" name="data_admissao" value="{{ $v('data_admissao') }}" required><div class="erro-campo">{{ $err('data_admissao') }}</div></div>
        <div class="campo"><label>Desligamento</label><input type="date" name="data_demissao" value="{{ $v('data_demissao') }}"><div class="erro-campo">{{ $err('data_demissao') }}</div></div>
        <div class="campo"><label>Salário (R$)</label><input name="salario" value="{{ old('salario', $colaborador->salario ? number_format($colaborador->salario, 2, ',', '.') : '') }}"><div class="erro-campo">{{ $err('salario') }}</div></div>
    </div></div>

    <div class="card"><h2>Jornada</h2>
        @if($jAtual)<p class="muted">Vigente: <strong>{{ $jAtual->jornada->nome }}</strong> desde {{ $jAtual->inicio->format('d/m/Y') }}. Para trocar, escolha outra jornada e a data de início; o histórico é mantido.</p>@endif
        <div class="campos">
            <div class="campo"><label>Jornada</label><select name="jornada_id"><option value="">—</option>@foreach($jornadas as $j)<option value="{{ $j->id }}" @selected(old('jornada_id', $jAtual?->jornada_id) == $j->id)>{{ $j->nome }}</option>@endforeach</select></div>
            <div class="campo"><label>Vigente a partir de</label><input type="date" name="jornada_inicio" value="{{ old('jornada_inicio', $jAtual?->inicio?->format('Y-m-d') ?? $v('data_admissao')) }}"><div class="erro-campo">{{ $err('jornada_inicio') }}</div></div>
            <div class="campo"><label>1º dia trabalhado (só 12x36)</label><input type="date" name="data_base_revezamento" value="{{ old('data_base_revezamento', $jAtual?->data_base_revezamento?->format('Y-m-d')) }}"></div>
        </div>
        @if($colaborador->exists && $colaborador->jornadas->count() > 1)
            <details style="margin-top:10px"><summary>Histórico de jornadas</summary><ul>
                @foreach($colaborador->jornadas as $cj)<li>{{ $cj->jornada->nome }}: {{ $cj->inicio->format('d/m/Y') }} até {{ $cj->fim?->format('d/m/Y') ?? 'hoje' }}</li>@endforeach
            </ul></details>
        @endif
    </div>

    <div class="card"><h2>Acesso ao sistema</h2>
        <p class="muted">Preencha o e-mail de login para o colaborador registrar ponto e ver o espelho. Sem senha informada, a senha inicial é o CPF (só números).</p>
        <div class="campos">
            <div class="campo"><label>E-mail de login</label><input type="email" name="acesso_email" value="{{ old('acesso_email', $colaborador->user?->email) }}"><div class="erro-campo">{{ $err('acesso_email') }}</div></div>
            <div class="campo"><label>Nova senha</label><input type="password" name="acesso_senha" autocomplete="new-password"><div class="erro-campo">{{ $err('acesso_senha') }}</div></div>
            <div class="campo"><label>Perfil</label><select name="acesso_perfil">@foreach(\App\Models\User::PERFIS as $k => $n)<option value="{{ $k }}" @selected(old('acesso_perfil', $colaborador->user?->perfil ?? 'colaborador') === $k)>{{ $n }}</option>@endforeach</select><div class="erro-campo">{{ $err('acesso_perfil') }}</div></div>
        </div>
    </div>

    <p><button class="btn">Salvar</button> <a class="btn sec" href="{{ route('colaboradores.index') }}">Cancelar</a></p>
</form>
@endsection
