<?php

namespace App\Http\Controllers\Ponto;

use App\Http\Controllers\Controller;
use App\Models\BancoHorasLancamento;
use App\Models\Colaborador;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BancoHorasController extends Controller
{
    public function index(Request $request)
    {
        $colaboradores = $this->colaboradoresVisiveis($request->user())->where('ativo', true)
            ->withSum('lancamentosBanco as saldo', 'minutos')->with('setor')->orderBy('nome')->get();
        $selecionado = $request->filled('colaborador_id') ? $colaboradores->firstWhere('id', (int) $request->colaborador_id) : null;
        $lancamentos = $selecionado ? $selecionado->lancamentosBanco()->orderByDesc('data')->orderByDesc('id')->get() : collect();
        return view('ponto.banco-horas', compact('colaboradores', 'selecionado', 'lancamentos'));
    }

    public function lancar(Request $request)
    {
        $dados = $request->validate([
            'colaborador_id' => 'required|exists:colaboradores,id',
            'data' => 'required|date',
            'tipo' => ['required', Rule::in(['compensacao', 'pagamento', 'ajuste'])],
            'sinal' => 'required|in:+,-',
            'horas' => ['required', 'regex:/^\d{1,3}:[0-5]\d$/'],
            'descricao' => 'required|max:255',
        ], ['horas.regex' => 'Informe as horas no formato HH:MM.']);

        [$h, $m] = array_map('intval', explode(':', $dados['horas']));
        $minutos = ($h * 60 + $m) * ($dados['sinal'] === '-' ? -1 : 1);

        BancoHorasLancamento::create([
            'colaborador_id' => $dados['colaborador_id'], 'data' => $dados['data'], 'tipo' => $dados['tipo'],
            'minutos' => $minutos, 'descricao' => $dados['descricao'], 'criado_por' => $request->user()->id,
        ]);
        return redirect()->route('banco-horas.index', ['colaborador_id' => $dados['colaborador_id']])->with('ok', 'Lançamento registrado.');
    }
}
