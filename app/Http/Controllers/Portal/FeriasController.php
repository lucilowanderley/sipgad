<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\FeriasPeriodo;
use App\Models\FeriasProgramacao;
use App\Models\Setor;
use App\Services\Ferias;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;

class FeriasController extends Controller
{
    /** Períodos aquisitivos e programações de um colaborador (o próprio, ou outro visível para gestor/RH/DP). */
    public function index(Request $request, Ferias $ferias)
    {
        $user = $request->user();
        $visiveis = $this->colaboradoresVisiveis($user)->where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'matricula']);
        $colaborador = $request->filled('colaborador_id')
            ? Colaborador::find($request->integer('colaborador_id'))
            : ($user->colaborador ?? null);
        if ($colaborador) {
            $this->autorizarColaborador($user, $colaborador);
        }
        $periodos = $colaborador ? $ferias->sincronizarPeriodos($colaborador)->sortByDesc('inicio')->values() : collect();
        $alertas = $colaborador ? $ferias->alertas(collect([$colaborador]))->keyBy(fn ($a) => $a->periodo->id) : collect();
        $podeProgramar = $colaborador && ($colaborador->user_id === $user->id || $user->ehGestaoPessoal());

        return view('ferias.index', compact('visiveis', 'colaborador', 'periodos', 'alertas', 'podeProgramar'));
    }

    public function store(Request $request, Ferias $ferias)
    {
        $user = $request->user();
        $dados = $request->validate([
            'periodo_id' => 'required|exists:ferias_periodos,id',
            'inicio' => 'required|date|after_or_equal:'.($user->ehGestaoPessoal() ? today()->subDays(60)->toDateString() : 'today'),
            'dias' => 'required|integer|min:5|max:30',
            'dias_abono' => 'nullable|integer|min:0|max:10',
            'adiantamento_13' => 'nullable|boolean',
            'observacao' => 'nullable|max:1000',
            'aprovar_direto' => 'nullable|boolean',
        ], ['inicio.after_or_equal' => 'A data de início não pode estar no passado.']);
        $periodo = FeriasPeriodo::with('colaborador')->findOrFail($dados['periodo_id']);
        $colaborador = $periodo->colaborador;
        abort_unless($colaborador->user_id === $user->id || $user->ehGestaoPessoal(), 403);

        try {
            $p = $ferias->programar($periodo, $dados, $user, $request->boolean('aprovar_direto') && $user->podeAprovarPara($colaborador));
        } catch (DomainException $e) {
            return back()->withInput()->with('erro', $e->getMessage());
        }
        $msg = $p->status === 'aprovada'
            ? 'Férias lançadas e aprovadas: '.$p->inicio->format('d/m/Y').' a '.$p->fim->format('d/m/Y').'.'
            : 'Férias de '.$p->inicio->format('d/m/Y').' a '.$p->fim->format('d/m/Y').' enviadas para aprovação.';
        return redirect()->route('ferias.index', ['colaborador_id' => $colaborador->id])->with('ok', $msg);
    }

    /** Pré-validação por AJAX enquanto o colaborador preenche o formulário. */
    public function simular(Request $request, Ferias $ferias)
    {
        $request->validate(['periodo_id' => 'required|exists:ferias_periodos,id', 'inicio' => 'required|date', 'dias' => 'required|integer|min:1|max:30', 'dias_abono' => 'nullable|integer|min:0|max:30']);
        $periodo = FeriasPeriodo::with('colaborador')->findOrFail($request->periodo_id);
        $this->autorizarColaborador($request->user(), $periodo->colaborador);
        $r = $ferias->validar($periodo, Carbon::parse($request->inicio), (int) $request->dias, (int) $request->dias_abono);
        return response()->json(['erros' => $r['erros'], 'avisos' => $r['avisos'], 'fim' => $r['fim']->format('d/m/Y'),
            'retorno' => $r['fim']->copy()->addDay()->format('d/m/Y')]);
    }

    public function cancelar(Request $request, FeriasProgramacao $programacao, Ferias $ferias)
    {
        try {
            $ferias->cancelar($programacao, $request->user());
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }
        return back()->with('ok', 'Programação de férias cancelada.');
    }

    /** RH/DP: faltas injustificadas (recalcula os dias pelo art. 130), perda do direito e observação. */
    public function periodo(Request $request, FeriasPeriodo $periodo)
    {
        $dados = $request->validate(['faltas_injustificadas' => 'required|integer|min:0|max:365', 'perdido' => 'nullable|boolean', 'observacao' => 'nullable|max:255']);
        $dias = Ferias::diasPorFaltas((int) $dados['faltas_injustificadas']);
        $usados = $periodo->load('programacoes')->diasUsados();
        if ($dias < $usados && ! $request->boolean('perdido')) {
            return back()->with('erro', "Com essas faltas o direito cai para {$dias} dias, mas já há {$usados} dias programados. Cancele alguma programação antes.");
        }
        $periodo->update(['faltas_injustificadas' => $dados['faltas_injustificadas'], 'dias_direito' => $dias,
            'perdido' => $request->boolean('perdido'), 'observacao' => $dados['observacao'] ?? null]);
        return back()->with('ok', "Período atualizado: {$dias} dias de direito.");
    }

    /** Controle geral: alertas de vencimento e próximas férias dos colaboradores visíveis. */
    public function controle(Request $request, Ferias $ferias)
    {
        $colaboradores = $this->colaboradoresVisiveis($request->user())->where('ativo', true)
            ->when($request->filled('setor_id'), fn ($q) => $q->where('setor_id', $request->integer('setor_id')))
            ->with('setor')->orderBy('nome')->get();
        $alertas = $ferias->alertas($colaboradores);
        $proximas = FeriasProgramacao::ativas()->whereIn('colaborador_id', $colaboradores->pluck('id'))
            ->whereDate('fim', '>=', today())->whereDate('inicio', '<=', today()->addDays(90))
            ->with('colaborador.setor')->orderBy('inicio')->get();
        $setores = Setor::orderBy('nome')->get();
        return view('ferias.controle', compact('alertas', 'proximas', 'setores'));
    }

    /** Cronograma anual do setor: barras com as férias de cada colaborador. */
    public function cronograma(Request $request, Ferias $ferias)
    {
        $user = $request->user();
        $ano = (int) $request->input('ano', today()->year);
        $ano = $ano >= 2000 && $ano <= 2100 ? $ano : today()->year;
        $podeTodos = $user->ehGestaoPessoal();
        $setores = $podeTodos ? Setor::with('empresa')->orderBy('nome')->get()
            : Setor::whereIn('id', $this->colaboradoresVisiveis($user)->pluck('setor_id')->push($user->colaborador?->setor_id)->filter()->unique())->get();
        $setorId = $request->integer('setor_id') ?: ($user->colaborador?->setor_id ?? $setores->first()?->id);
        abort_unless(! $setorId || $setores->contains('id', $setorId), 403);

        $inicioAno = Carbon::create($ano, 1, 1)->startOfDay();
        $fimAno = $inicioAno->copy()->endOfYear()->startOfDay();
        $colaboradores = Colaborador::where('ativo', true)->where('setor_id', $setorId)->orderBy('nome')
            ->with(['feriasProgramacoes' => fn ($q) => $q->ativas()->whereDate('inicio', '<=', $fimAno)->whereDate('fim', '>=', $inicioAno)])
            ->get();
        foreach ($colaboradores as $c) {
            $ferias->sincronizarPeriodos($c);
        }
        $colaboradores->load(['feriasPeriodos' => fn ($q) => $q->with('programacoes')]);

        // dias com mais de uma pessoa do setor ausente
        $ocupacao = [];
        foreach ($colaboradores as $c) {
            foreach ($c->feriasProgramacoes as $p) {
                for ($d = $p->inicio->copy()->max($inicioAno); $d->lte($p->fim) && $d->lte($fimAno); $d->addDay()) {
                    $ocupacao[$d->dayOfYear] = ($ocupacao[$d->dayOfYear] ?? 0) + 1;
                }
            }
        }
        $diasAno = $inicioAno->daysInYear;
        $conflitos = collect($ocupacao)->filter(fn ($n) => $n > 1)->keys()->sort()->values();

        return view('ferias.cronograma', compact('ano', 'setores', 'setorId', 'colaboradores', 'inicioAno', 'fimAno', 'diasAno', 'conflitos'));
    }
}
