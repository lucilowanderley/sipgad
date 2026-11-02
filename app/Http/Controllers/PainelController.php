<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Portal\DocumentoController;
use App\Models\Colaborador;
use App\Models\Comunicado;
use App\Models\Documento;
use App\Models\FeriasProgramacao;
use App\Models\Marcacao;
use App\Models\Solicitacao;
use App\Models\TipoDocumento;
use App\Services\Apuracao;
use App\Services\Ferias;

class PainelController extends Controller
{
    public function __invoke(Apuracao $apuracao, Ferias $ferias)
    {
        $user = auth()->user();
        $colaborador = $user->colaborador;
        $dados = ['colaborador' => $colaborador];

        if ($colaborador) {
            $dados['hoje'] = $colaborador->marcacoes()->validas()->whereDate('data_hora', today())->orderBy('data_hora')->get();
            $dados['saldoBanco'] = $colaborador->saldoBancoMinutos();
            $dados['mes'] = $apuracao->periodo($colaborador, now()->startOfMonth(), today())['totais'];

            // Portal: pendências pessoais
            $dados['minhasPendentes'] = $colaborador->solicitacoes()->pendentes()->count()
                + $colaborador->feriasProgramacoes()->where('status', 'pendente')->count();
            $dados['proximasFerias'] = $colaborador->feriasProgramacoes()->ativas()->whereDate('fim', '>=', today())->orderBy('inicio')->first();
            $dados['alertaFerias'] = $ferias->alertas(collect([$colaborador]))->first();
            $vigentes = DocumentoController::vigentes($colaborador->documentos()->with('tipo')->get());
            $dados['meusDocsAlerta'] = $vigentes->filter(fn ($d) => in_array($d->situacao(), ['vencido', 'vencendo'], true))->values();
            $dados['meusDocsFaltando'] = TipoDocumento::where('obrigatorio', true)->whereNotIn('id', $vigentes->keys())->pluck('nome');
        }

        $dados['comunicados'] = Comunicado::visiveisPara($user)->with(['autor', 'leituras' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderByDesc('fixado')->orderByDesc('publicar_em')->limit(3)->get();

        if ($user->aprova()) {
            $ids = $user->aprovaveis()->pluck('id');
            $dados['aprovacoesPendentes'] = Solicitacao::pendentes()->whereIn('colaborador_id', $ids)->count()
                + FeriasProgramacao::where('status', 'pendente')->whereIn('colaborador_id', $ids)->count();
            $equipe = $this->colaboradoresVisiveis($user)->where('ativo', true)->get();
            $alertas = $ferias->alertas($equipe);
            $dados['feriasCriticas'] = $alertas->whereIn('nivel', ['vencido', 'critico'])->count();
            $dados['emFerias'] = FeriasProgramacao::where('status', 'aprovada')->whereIn('colaborador_id', $equipe->pluck('id'))
                ->whereDate('inicio', '<=', today())->whereDate('fim', '>=', today())->count();
        }
        if ($user->ehGestaoPessoal()) {
            $dados['docsVencidos'] = Colaborador::where('ativo', true)->with('documentos.tipo')->get()
                ->sum(fn ($c) => DocumentoController::vigentes($c->documentos)->filter(fn ($d) => $d->situacao() === 'vencido')->count());
            $dados['docsAConferir'] = Documento::whereNull('conferido_em')->count();
        }

        if ($user->ehGestaoPessoal() || $user->temPerfil('gestor')) {
            $base = Colaborador::where('ativo', true);
            if (! $user->ehGestaoPessoal()) {
                $base->where('gestor_id', $colaborador?->id ?? 0);
            }
            $ids = (clone $base)->pluck('id');
            $presentes = Marcacao::validas()->whereIn('colaborador_id', $ids)->whereDate('data_hora', today())->distinct()->pluck('colaborador_id');
            $dados['indicadores'] = [
                'ativos' => $ids->count(),
                'presentes' => $presentes->count(),
                'marcacoes_hoje' => Marcacao::validas()->whereIn('colaborador_id', $ids)->whereDate('data_hora', today())->count(),
            ];
            $dados['semPonto'] = (clone $base)->whereNotIn('id', $presentes)->with('setor')->orderBy('nome')->limit(15)->get();
        }

        return view('painel', $dados);
    }
}
