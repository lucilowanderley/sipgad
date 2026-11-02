<?php

namespace App\Http\Controllers\Ponto;

use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\Marcacao;
use App\Models\PeriodoFechamento;
use App\Services\RegistroPonto;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;

/** Ajustes feitos pelo DP. A marcação original nunca é apagada. */
class AjusteController extends Controller
{
    public function incluir(Request $request, RegistroPonto $registro)
    {
        $dados = $request->validate([
            'colaborador_id' => 'required|exists:colaboradores,id',
            'data' => 'required|date|before_or_equal:today',
            'hora' => 'required|date_format:H:i',
            'motivo' => 'required|min:5|max:255',
        ]);
        $colaborador = Colaborador::findOrFail($dados['colaborador_id']);
        $quando = Carbon::parse($dados['data'].' '.$dados['hora']);
        abort_if($quando->isFuture(), 422, 'Não é possível incluir marcação no futuro.');

        try {
            $registro->registrar($colaborador, $quando, [
                'origem' => 'ajuste', 'motivo' => $dados['motivo'],
                'criado_por' => $request->user()->id, 'ip' => $request->ip(),
            ]);
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }
        return back()->with('ok', 'Marcação incluída em '.$quando->format('d/m/Y H:i').'.');
    }

    public function desconsiderar(Request $request, Marcacao $marcacao)
    {
        $dados = $request->validate(['motivo' => 'required|min:5|max:255']);
        if (PeriodoFechamento::fechado($marcacao->empresa_id, $marcacao->data_hora->toDateString())) {
            return back()->with('erro', 'Período fechado; reabra a competência para ajustar.');
        }
        if ($marcacao->desconsiderada_em) {
            return back()->with('erro', 'Marcação já desconsiderada.');
        }
        $marcacao->update([
            'desconsiderada_em' => now(),
            'motivo_desconsideracao' => $dados['motivo'],
            'desconsiderada_por' => $request->user()->id,
        ]);
        return back()->with('ok', 'Marcação desconsiderada.');
    }
}
