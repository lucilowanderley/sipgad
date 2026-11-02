<?php

namespace App\Http\Controllers\Ponto;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\ImportacaoAfd;
use App\Models\Relogio;
use App\Services\Afd\ImportadorAfd;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Cadastro de relógios de ponto físicos e coleta das marcações via AFD. */
class RelogioController extends Controller
{
    public function index()
    {
        return view('ponto.relogios.index', [
            'relogios' => Relogio::with('empresa')->withCount('marcacoes')->orderBy('nome')->get(),
        ]);
    }

    public function create()
    {
        return view('ponto.relogios.form', ['relogio' => new Relogio, 'empresas' => Empresa::orderBy('razao_social')->get()]);
    }

    public function store(Request $request)
    {
        $relogio = Relogio::create($this->validar($request, new Relogio));
        return redirect()->route('relogios.show', $relogio)->with('ok', 'Relógio cadastrado. Agora importe o AFD coletado dele.');
    }

    public function edit(Relogio $relogio)
    {
        return view('ponto.relogios.form', ['relogio' => $relogio, 'empresas' => Empresa::orderBy('razao_social')->get()]);
    }

    public function update(Request $request, Relogio $relogio)
    {
        $relogio->update($this->validar($request, $relogio));
        return redirect()->route('relogios.show', $relogio)->with('ok', 'Relógio atualizado.');
    }

    public function show(Relogio $relogio)
    {
        return view('ponto.relogios.show', [
            'relogio' => $relogio->load('empresa'),
            'importacoes' => $relogio->importacoes()->with('user')->paginate(15),
            'ultimas' => $relogio->marcacoes()->with('colaborador')->latest('data_hora')->limit(10)->get(),
        ]);
    }

    public function importar(Request $request, Relogio $relogio, ImportadorAfd $importador)
    {
        $request->validate(['arquivo' => 'required|file|max:51200'], ['arquivo.required' => 'Selecione o arquivo AFD (.txt) gerado pelo relógio.']);
        $arquivo = $request->file('arquivo');

        try {
            $imp = $importador->importar($relogio, file_get_contents($arquivo->getRealPath()), $arquivo->getClientOriginalName(), 'upload', $request->user()->id);
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }
        return back()->with($imp->pendentes ? 'erro' : 'ok', $this->resumo($imp));
    }

    public function reprocessar(Request $request, ImportacaoAfd $importacao, ImportadorAfd $importador)
    {
        try {
            $imp = $importador->reprocessar($importacao, $request->user()->id);
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }
        return back()->with($imp->pendentes ? 'erro' : 'ok', $this->resumo($imp));
    }

    public function arquivo(ImportacaoAfd $importacao)
    {
        abort_unless(Storage::disk('local')->exists($importacao->arquivo_path), 404);
        return Storage::disk('local')->download($importacao->arquivo_path, $importacao->arquivo_nome);
    }

    /** Verifica se o relógio responde na rede (porta TCP configurada). */
    public function testar(Relogio $relogio)
    {
        if (! $relogio->ip) {
            return back()->with('erro', 'Informe o IP do relógio no cadastro para testar a conexão.');
        }
        $porta = $relogio->porta ?: 80;
        $inicio = microtime(true);
        $sock = @fsockopen($relogio->ip, $porta, $errno, $errstr, 3);
        if (! $sock) {
            return back()->with('erro', "Sem resposta de {$relogio->ip}:{$porta} ({$errstr}). Confira o cabo de rede, o IP configurado no relógio e se este servidor está na mesma rede.");
        }
        fclose($sock);
        $ms = (int) round((microtime(true) - $inicio) * 1000);
        return back()->with('ok', "O relógio respondeu em {$relogio->ip}:{$porta} ({$ms} ms).");
    }

    private function validar(Request $request, Relogio $relogio): array
    {
        $dados = $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'nome' => 'required|max:100',
            'fabricante' => 'required|max:60',
            'modelo' => 'nullable|max:60',
            'tipo_rep' => ['required', Rule::in(array_keys(Relogio::TIPOS_REP))],
            'numero_fabricacao' => ['nullable', 'regex:/^\d{1,17}$/', Rule::unique('relogios')->where('fabricante', $request->fabricante)->ignore($relogio->id)],
            'local' => 'nullable|max:255',
            'ip' => 'nullable|ip',
            'porta' => 'nullable|integer|between:1,65535',
            'pasta_afd' => 'nullable|max:255',
        ], ['numero_fabricacao.regex' => 'Use só os dígitos do número de fabricação (até 17).']);
        $dados['ativo'] = $request->boolean('ativo');
        return $dados;
    }

    private function resumo(ImportacaoAfd $imp): string
    {
        $txt = "{$imp->marcacoes_arquivo} marcações no arquivo: {$imp->importadas} importadas, {$imp->duplicadas} já existiam";
        return $txt.($imp->pendentes ? ", {$imp->pendentes} pendentes (veja o detalhe abaixo)." : '.');
    }
}
