<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\Documento;
use App\Models\TipoDocumento;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Dossiê digital. Acesso: o próprio colaborador e RH/DP/admin. Gestores não veem (documentos pessoais, LGPD).
 */
class DocumentoController extends Controller
{
    private function autorizar(User $user, Colaborador $colaborador): void
    {
        abort_unless($user->ehGestaoPessoal() || $colaborador->user_id === $user->id, 403, 'Sem acesso a este dossiê.');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $colaborador = $request->filled('colaborador_id') ? Colaborador::findOrFail($request->integer('colaborador_id')) : $user->colaborador;
        if (! $colaborador && $user->ehGestaoPessoal()) {
            return redirect()->route('documentos.vencimentos');
        }
        abort_unless($colaborador, 403, 'Seu usuário não está vinculado a um colaborador.');
        $this->autorizar($user, $colaborador);

        $tipos = TipoDocumento::orderBy('nome')->get();
        $documentos = $colaborador->documentos()->with('tipo', 'remetente')->orderByDesc('created_at')->get();
        $vigentes = self::vigentes($documentos);
        $resumo = $tipos->map(fn ($t) => (object) ['tipo' => $t, 'doc' => $vigentes->get($t->id),
            'situacao' => $vigentes->get($t->id)?->situacao() ?? ($t->obrigatorio ? 'faltando' : null)]);
        $colaboradores = $user->ehGestaoPessoal() ? Colaborador::where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'matricula']) : collect();
        $tiposEnvio = $user->ehGestaoPessoal() ? $tipos : $tipos->where('colaborador_envia', true);

        return view('documentos.index', compact('colaborador', 'documentos', 'vigentes', 'resumo', 'colaboradores', 'tiposEnvio'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $dados = $request->validate([
            'colaborador_id' => 'required|exists:colaboradores,id',
            'tipo_documento_id' => 'required|exists:tipos_documento,id',
            'descricao' => 'nullable|max:255',
            'numero' => 'nullable|max:60',
            'emissao' => 'nullable|date|before_or_equal:today',
            'validade' => 'nullable|date|after_or_equal:emissao',
            'arquivo' => 'required|file|max:15360|mimes:pdf,jpg,jpeg,png,webp,heic,doc,docx,xls,xlsx,odt',
        ], ['arquivo.mimes' => 'Formatos aceitos: PDF, imagem (JPG/PNG/WEBP/HEIC), Word, Excel ou ODT.']);
        $colaborador = Colaborador::findOrFail($dados['colaborador_id']);
        $this->autorizar($user, $colaborador);
        $tipo = TipoDocumento::findOrFail($dados['tipo_documento_id']);
        abort_unless($user->ehGestaoPessoal() || $tipo->colaborador_envia, 403, 'Este tipo de documento é enviado só pelo RH.');
        if ($tipo->exige_validade && empty($dados['validade'])) {
            return back()->withInput()->withErrors(['validade' => "{$tipo->nome} exige data de validade."]);
        }

        $arq = $request->file('arquivo');
        $caminho = $arq->storeAs("dossie/{$colaborador->id}", Str::uuid().'.'.$arq->extension(), 'local');
        Documento::create(array_merge($dados, [
            'arquivo_path' => $caminho, 'arquivo_nome' => mb_substr($arq->getClientOriginalName(), 0, 250),
            'mime' => $arq->getMimeType(), 'tamanho' => $arq->getSize(), 'hash' => hash_file('sha256', $arq->getRealPath()),
            'enviado_por' => $user->id,
            // enviado pelo RH já entra conferido
            'conferido_em' => $user->ehGestaoPessoal() ? now() : null, 'conferido_por' => $user->ehGestaoPessoal() ? $user->id : null,
        ]));

        return redirect()->route('documentos.index', ['colaborador_id' => $colaborador->id])->with('ok', "{$tipo->nome} anexado ao dossiê.");
    }

    public function baixar(Request $request, Documento $documento)
    {
        $this->autorizar($request->user(), $documento->colaborador);
        abort_unless(Storage::disk('local')->exists($documento->arquivo_path), 404, 'Arquivo não encontrado no servidor.');
        $disposicao = $request->boolean('ver') ? 'inline' : 'attachment';
        return Storage::disk('local')->response($documento->arquivo_path, $documento->arquivo_nome, [], $disposicao);
    }

    public function conferir(Request $request, Documento $documento)
    {
        abort_unless($request->user()->ehGestaoPessoal(), 403);
        $documento->update(['conferido_em' => now(), 'conferido_por' => $request->user()->id]);
        return back()->with('ok', 'Documento conferido.');
    }

    /** Exclusão lógica: o arquivo é mantido no servidor e o registro fica na auditoria. */
    public function destroy(Request $request, Documento $documento)
    {
        abort_unless($request->user()->ehGestaoPessoal(), 403);
        $documento->delete();
        return back()->with('ok', 'Documento removido do dossiê.');
    }

    /** RH/DP: documentos vencidos ou a vencer e obrigatórios faltando. */
    public function vencimentos(Request $request)
    {
        $colaboradores = Colaborador::where('ativo', true)->with(['documentos.tipo', 'setor'])->orderBy('nome')->get();
        $obrigatorios = TipoDocumento::where('obrigatorio', true)->orderBy('nome')->get();
        $vencendo = collect();
        $faltando = collect();
        foreach ($colaboradores as $c) {
            $vig = self::vigentes($c->documentos);
            foreach ($vig as $doc) {
                if (in_array($doc->situacao(), ['vencido', 'vencendo'], true)) {
                    $vencendo->push($doc->setRelation('colaborador', $c));
                }
            }
            $falta = $obrigatorios->reject(fn ($t) => $vig->has($t->id));
            if ($falta->isNotEmpty()) {
                $faltando->push((object) ['colaborador' => $c, 'tipos' => $falta]);
            }
        }
        $vencendo = $vencendo->sortBy('validade')->values();
        $pendentesConferencia = Documento::whereNull('conferido_em')->with('colaborador', 'tipo')->latest()->limit(50)->get();
        return view('documentos.vencimentos', compact('vencendo', 'faltando', 'pendentesConferencia'));
    }

    /** Documento mais recente de cada tipo (os anteriores ficam como histórico e não geram alerta). */
    public static function vigentes(Collection $documentos): Collection
    {
        return $documentos->sortByDesc(fn ($d) => sprintf("%012d-%012d", $d->created_at?->timestamp ?? 0, $d->id))
            ->unique('tipo_documento_id')->keyBy('tipo_documento_id');
    }
}
