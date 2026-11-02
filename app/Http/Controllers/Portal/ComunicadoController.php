<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Comunicado;
use App\Models\ComunicadoLeitura;
use App\Models\Empresa;
use App\Models\Setor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComunicadoController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $comunicados = Comunicado::visiveisPara($user)->with(['autor', 'setor', 'empresa',
            'leituras' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderByDesc('fixado')->orderByDesc('publicar_em')->paginate(15);
        // RH/DP também veem agendados e expirados para gerenciar
        $gerenciar = $user->ehGestaoPessoal()
            ? Comunicado::where(fn ($q) => $q->where('publicar_em', '>', now())->orWhere('expira_em', '<', today()->toDateString()))
                ->orderByDesc('publicar_em')->limit(20)->get()
            : collect();
        return view('mural.index', compact('comunicados', 'gerenciar'));
    }

    public function show(Request $request, Comunicado $comunicado)
    {
        $user = $request->user();
        abort_unless($user->ehGestaoPessoal() || Comunicado::visiveisPara($user)->whereKey($comunicado->id)->exists(), 404);
        $leitura = ComunicadoLeitura::firstOrCreate(['comunicado_id' => $comunicado->id, 'user_id' => $user->id], ['lido_em' => now(), 'ip' => $request->ip()]);
        return view('mural.show', compact('comunicado', 'leitura'));
    }

    public function ciencia(Request $request, Comunicado $comunicado)
    {
        $user = $request->user();
        abort_unless(Comunicado::visiveisPara($user)->whereKey($comunicado->id)->exists(), 404);
        $leitura = ComunicadoLeitura::firstOrCreate(['comunicado_id' => $comunicado->id, 'user_id' => $user->id], ['lido_em' => now()]);
        if (! $leitura->ciente_em) {
            $leitura->update(['ciente_em' => now(), 'ip' => $request->ip()]);
        }
        return back()->with('ok', 'Ciência registrada em '.$leitura->ciente_em->format('d/m/Y H:i').'.');
    }

    public function create()
    {
        return $this->form(new Comunicado(['publicar_em' => now()]));
    }

    public function edit(Comunicado $comunicado)
    {
        return $this->form($comunicado);
    }

    private function form(Comunicado $comunicado)
    {
        return view('mural.form', ['comunicado' => $comunicado, 'empresas' => Empresa::orderBy('razao_social')->get(),
            'setores' => Setor::with('empresa')->orderBy('nome')->get()]);
    }

    public function store(Request $request)
    {
        $c = Comunicado::create($this->dados($request) + ['autor_id' => $request->user()->id]);
        return redirect()->route('mural.show', $c)->with('ok', $c->publicar_em->isFuture() ? 'Comunicado agendado para '.$c->publicar_em->format('d/m/Y H:i').'.' : 'Comunicado publicado.');
    }

    public function update(Request $request, Comunicado $comunicado)
    {
        $comunicado->update($this->dados($request));
        return redirect()->route('mural.show', $comunicado)->with('ok', 'Comunicado atualizado.');
    }

    public function destroy(Comunicado $comunicado)
    {
        $comunicado->delete();
        return redirect()->route('mural.index')->with('ok', 'Comunicado excluído.');
    }

    public function leituras(Comunicado $comunicado)
    {
        $leituras = $comunicado->leituras()->with('user.colaborador.setor')->get()->keyBy('user_id');
        $publico = $comunicado->publico()->with('colaborador.setor')->orderBy('name')->get();
        $pendentes = $publico->reject(fn ($u) => $comunicado->exige_ciencia ? $leituras->get($u->id)?->ciente_em : $leituras->has($u->id));
        return view('mural.leituras', compact('comunicado', 'leituras', 'publico', 'pendentes'));
    }

    private function dados(Request $request): array
    {
        $d = $request->validate([
            'titulo' => 'required|max:255',
            'corpo' => 'required|max:20000',
            'categoria' => ['required', Rule::in(array_keys(Comunicado::CATEGORIAS))],
            'empresa_id' => 'nullable|exists:empresas,id',
            'setor_id' => 'nullable|exists:setores,id',
            'publicar_em' => 'required|date',
            'expira_em' => 'nullable|date|after_or_equal:publicar_em',
        ]);
        $d['fixado'] = $request->boolean('fixado');
        $d['exige_ciencia'] = $request->boolean('exige_ciencia');
        if (! empty($d['setor_id'])) {
            $d['empresa_id'] = Setor::find($d['setor_id'])->empresa_id;
        }
        return $d;
    }
}
