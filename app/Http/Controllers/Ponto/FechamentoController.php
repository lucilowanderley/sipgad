<?php

namespace App\Http\Controllers\Ponto;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\PeriodoFechamento;
use App\Services\Fechamento;
use DomainException;
use Illuminate\Http\Request;

class FechamentoController extends Controller
{
    public function index()
    {
        return view('ponto.fechamentos', [
            'periodos' => PeriodoFechamento::with('empresa', 'responsavel')->orderByDesc('inicio')->get(),
            'empresas' => Empresa::orderBy('razao_social')->get(),
        ]);
    }

    public function fechar(Request $request, Fechamento $fechamento)
    {
        $dados = $request->validate(['empresa_id' => 'required|exists:empresas,id', 'competencia' => 'required|date_format:Y-m']);
        try {
            $fechamento->fechar(Empresa::findOrFail($dados['empresa_id']), $dados['competencia'], $request->user()->id);
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }
        return back()->with('ok', 'Competência fechada e saldos lançados no banco de horas.');
    }

    public function reabrir(PeriodoFechamento $periodo, Fechamento $fechamento)
    {
        try {
            $fechamento->reabrir($periodo);
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }
        return back()->with('ok', 'Competência reaberta; os lançamentos de banco de horas dela foram removidos.');
    }
}
