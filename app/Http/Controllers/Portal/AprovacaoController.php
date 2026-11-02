<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\FeriasProgramacao;
use App\Models\Solicitacao;
use App\Services\Ferias;
use App\Services\Solicitacoes;
use DomainException;
use Illuminate\Http\Request;

/** Caixa de aprovação do gestor (equipe direta) e do RH/DP (todos). */
class AprovacaoController extends Controller
{
    public function index(Request $request, Ferias $ferias)
    {
        $user = $request->user();
        $ids = $user->aprovaveis()->pluck('id');

        $solicitacoes = Solicitacao::pendentes()->whereIn('colaborador_id', $ids)
            ->with('colaborador.setor', 'colaborador.gestor', 'autor')->orderBy('created_at')->get();
        $programacoes = FeriasProgramacao::where('status', 'pendente')->whereIn('colaborador_id', $ids)
            ->with('colaborador.setor', 'colaborador.gestor', 'periodo.programacoes')->orderBy('inicio')->get();
        // avisos (antecedência, concessivo, colegas do setor) para quem decide
        $avisosFerias = $programacoes->mapWithKeys(fn ($p) => [$p->id => $ferias->validar($p->periodo, $p->inicio, $p->dias, $p->dias_abono, $p->id)]);

        $historico = Solicitacao::whereIn('colaborador_id', $ids)->whereNotIn('status', ['pendente'])
            ->where('decidido_em', '>=', now()->subDays(30))->with('colaborador', 'decisor')->latest('decidido_em')->limit(30)->get();

        return view('portal.aprovacoes', compact('solicitacoes', 'programacoes', 'avisosFerias', 'historico'));
    }

    public function solicitacao(Request $request, Solicitacao $solicitacao, Solicitacoes $servico)
    {
        $dados = $request->validate(['decisao' => 'required|in:aprovar,rejeitar', 'resposta' => 'required_if:decisao,rejeitar|nullable|max:1000'],
            ['resposta.required_if' => 'Explique o motivo da rejeição.']);
        try {
            $dados['decisao'] === 'aprovar'
                ? $servico->aprovar($solicitacao, $request->user(), $dados['resposta'] ?? null)
                : $servico->rejeitar($solicitacao, $request->user(), $dados['resposta']);
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }
        return back()->with('ok', 'Solicitação de '.$solicitacao->colaborador->nome.' '.($dados['decisao'] === 'aprovar' ? 'aprovada' : 'rejeitada').'.');
    }

    public function ferias(Request $request, FeriasProgramacao $programacao, Ferias $servico)
    {
        $dados = $request->validate(['decisao' => 'required|in:aprovar,rejeitar', 'resposta' => 'required_if:decisao,rejeitar|nullable|max:1000'],
            ['resposta.required_if' => 'Explique o motivo da rejeição.']);
        try {
            $dados['decisao'] === 'aprovar'
                ? $servico->aprovar($programacao, $request->user(), $dados['resposta'] ?? null)
                : $servico->rejeitar($programacao, $request->user(), $dados['resposta']);
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }
        return back()->with('ok', 'Férias de '.$programacao->colaborador->nome.' '.($dados['decisao'] === 'aprovar' ? 'aprovadas' : 'rejeitadas').'.');
    }
}
