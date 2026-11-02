<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\Solicitacao;
use App\Services\Solicitacoes;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SolicitacaoController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $proprias = $user->colaborador
            ? $user->colaborador->solicitacoes()->with('decisor')->latest()->paginate(20)
            : null;
        // RH/DP podem registrar em nome de qualquer colaborador (ex.: atestado entregue no balcão)
        $colaboradores = $user->ehGestaoPessoal() ? Colaborador::where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'matricula']) : collect();
        return view('portal.solicitacoes', compact('proprias', 'colaboradores'));
    }

    public function store(Request $request, Solicitacoes $servico)
    {
        $user = $request->user();
        $dados = $request->validate([
            'colaborador_id' => 'nullable|exists:colaboradores,id',
            'tipo' => ['required', Rule::in(array_keys(Solicitacao::TIPOS))],
            'data' => 'required|date|before_or_equal:'.($request->tipo === 'ajuste' ? 'today' : today()->addDays(60)->toDateString()),
            'data_fim' => 'nullable|date|after_or_equal:data|before_or_equal:'.today()->addDays(120)->toDateString(),
            'hora' => 'required_if:tipo,ajuste|nullable|date_format:H:i',
            'periodo' => 'nullable|in:dia,parcial',
            'horas' => ['required_if:periodo,parcial', 'nullable', 'regex:/^\d{1,2}:[0-5]\d$/'],
            'cid' => 'nullable|max:10',
            'motivo' => 'required|min:5|max:2000',
            'anexo' => ($request->tipo === 'atestado' ? 'required' : 'nullable').'|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,heic',
            'aprovar_direto' => 'nullable|boolean',
        ], [
            'hora.required_if' => 'Informe o horário que deveria ter sido registrado.',
            'horas.required_if' => 'Informe quantas horas abonar (HH:MM).',
            'anexo.required' => 'Anexe o atestado (foto ou PDF).',
            'data.before_or_equal' => 'Data fora do prazo permitido.',
        ]);

        $colaborador = $user->ehGestaoPessoal() && ! empty($dados['colaborador_id'])
            ? Colaborador::findOrFail($dados['colaborador_id'])
            : $user->colaborador;
        abort_unless($colaborador, 403, 'Seu usuário não está vinculado a um colaborador.');

        $minutos = null;
        if ($dados['tipo'] !== 'ajuste' && ($dados['periodo'] ?? 'dia') === 'parcial') {
            [$h, $m] = array_map('intval', explode(':', $dados['horas']));
            $minutos = $h * 60 + $m;
            if ($minutos < 1) {
                return back()->withInput()->withErrors(['horas' => 'Informe um tempo maior que zero.']);
            }
        }
        $dataFim = $dados['tipo'] === 'ajuste' || $minutos ? null : ($dados['data_fim'] ?? null);

        $s = Solicitacao::create([
            'colaborador_id' => $colaborador->id, 'tipo' => $dados['tipo'], 'data' => $dados['data'], 'data_fim' => $dataFim,
            'hora' => $dados['tipo'] === 'ajuste' ? $dados['hora'] : null, 'minutos' => $minutos,
            'cid' => $dados['tipo'] === 'atestado' ? ($dados['cid'] ?? null) : null,
            'motivo' => $dados['motivo'], 'criado_por' => $user->id,
        ]);
        if ($request->hasFile('anexo')) {
            $arq = $request->file('anexo');
            $s->update([
                'anexo_path' => $arq->storeAs("solicitacoes/{$s->id}", 'anexo-'.now()->format('YmdHis').'.'.$arq->extension(), 'local'),
                'anexo_nome' => mb_substr($arq->getClientOriginalName(), 0, 250),
            ]);
        }

        if ($request->boolean('aprovar_direto') && $user->podeAprovarPara($colaborador)) {
            try {
                $servico->aprovar($s, $user, 'Lançado e aprovado pelo '.(\App\Models\User::PERFIS[$user->perfil] ?? 'RH').'.');
            } catch (DomainException $e) {
                return redirect()->route('solicitacoes.index')->with('erro', 'Solicitação registrada, mas não aprovada: '.$e->getMessage());
            }
            return redirect()->route('solicitacoes.index')->with('ok', 'Solicitação registrada e aprovada.');
        }

        $destino = $colaborador->gestor ? 'ao gestor '.$colaborador->gestor->nome : 'ao RH/DP';
        return redirect()->route('solicitacoes.index')->with('ok', "Solicitação enviada {$destino} para aprovação.");
    }

    public function cancelar(Request $request, Solicitacao $solicitacao, Solicitacoes $servico)
    {
        try {
            $servico->cancelar($solicitacao, $request->user());
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }
        return back()->with('ok', 'Solicitação cancelada.');
    }

    public function anexo(Request $request, Solicitacao $solicitacao)
    {
        $user = $request->user();
        $permitido = $solicitacao->colaborador->user_id === $user->id || $user->ehGestaoPessoal() || $user->podeAprovarPara($solicitacao->colaborador);
        abort_unless($permitido && $solicitacao->anexo_path && Storage::disk('local')->exists($solicitacao->anexo_path), 404);
        return Storage::disk('local')->response($solicitacao->anexo_path, $solicitacao->anexo_nome);
    }
}
