<?php

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\Empresa;
use App\Models\Feriado;
use App\Models\Setor;
use App\Models\TipoDocumento;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD genérico para cadastros de poucos campos: empresas, setores, cargos e feriados.
 * O recurso vem do nome da rota (ex.: setores.index).
 */
class CadastroSimplesController extends Controller
{
    private function config(string $recurso): array
    {
        $empresas = fn () => Empresa::orderBy('razao_social')->get()->mapWithKeys(fn ($e) => [$e->id => $e->nome])->all();

        return match ($recurso) {
            'empresas' => [
                'model' => Empresa::class, 'titulo' => 'Empresas', 'singular' => 'empresa', 'ordem' => 'razao_social',
                'colunas' => ['razao_social' => 'Razão social', 'cnpj' => 'CNPJ', 'cidade' => 'Cidade'],
                'campos' => [
                    'razao_social' => ['label' => 'Razão social', 'regra' => 'required|max:255'],
                    'nome_fantasia' => ['label' => 'Nome fantasia', 'regra' => 'nullable|max:255'],
                    'cnpj' => ['label' => 'CNPJ', 'regra' => fn ($id) => ['required', 'max:18', Rule::unique('empresas')->ignore($id)], 'mascara' => 'cnpj'],
                    'endereco' => ['label' => 'Endereço', 'regra' => 'nullable|max:255'],
                    'cidade' => ['label' => 'Cidade', 'regra' => 'nullable|max:255'],
                    'uf' => ['label' => 'UF', 'regra' => 'nullable|size:2'],
                    'tolerancia_diaria_minutos' => ['label' => 'Tolerância diária (min)', 'tipo' => 'number', 'regra' => 'required|integer|min:0|max:60', 'padrao' => 10],
                    'banco_horas_ativo' => ['label' => 'Usa banco de horas', 'tipo' => 'checkbox', 'regra' => 'boolean', 'padrao' => true],
                ],
            ],
            'setores' => [
                'model' => Setor::class, 'titulo' => 'Setores', 'singular' => 'setor', 'ordem' => 'nome', 'com' => ['empresa'],
                'colunas' => ['nome' => 'Nome', 'empresa.nome' => 'Empresa'],
                'campos' => [
                    'empresa_id' => ['label' => 'Empresa', 'tipo' => 'select', 'opcoes' => $empresas, 'regra' => 'required|exists:empresas,id'],
                    'nome' => ['label' => 'Nome', 'regra' => 'required|max:255'],
                ],
            ],
            'cargos' => [
                'model' => Cargo::class, 'titulo' => 'Cargos', 'singular' => 'cargo', 'ordem' => 'nome', 'com' => ['empresa'],
                'colunas' => ['nome' => 'Nome', 'cbo' => 'CBO', 'empresa.nome' => 'Empresa'],
                'campos' => [
                    'empresa_id' => ['label' => 'Empresa', 'tipo' => 'select', 'opcoes' => $empresas, 'regra' => 'required|exists:empresas,id'],
                    'nome' => ['label' => 'Nome', 'regra' => 'required|max:255'],
                    'cbo' => ['label' => 'CBO', 'regra' => 'nullable|max:10'],
                ],
            ],
            'feriados' => [
                'model' => Feriado::class, 'titulo' => 'Feriados', 'singular' => 'feriado', 'ordem' => 'data', 'com' => ['empresa'],
                'colunas' => ['data' => 'Data', 'descricao' => 'Descrição', 'empresa.nome' => 'Empresa'],
                'campos' => [
                    'data' => ['label' => 'Data', 'tipo' => 'date', 'regra' => 'required|date'],
                    'descricao' => ['label' => 'Descrição', 'regra' => 'required|max:255'],
                    'empresa_id' => ['label' => 'Empresa (vazio = todas)', 'tipo' => 'select', 'opcoes' => $empresas, 'regra' => 'nullable|exists:empresas,id'],
                ],
            ],
            'tipos-documento' => [
                'model' => TipoDocumento::class, 'titulo' => 'Tipos de documento', 'singular' => 'tipo de documento', 'ordem' => 'nome',
                'colunas' => ['nome' => 'Nome', 'obrigatorio' => 'Obrigatório', 'exige_validade' => 'Tem validade', 'alerta_dias' => 'Alerta (dias)'],
                'campos' => [
                    'nome' => ['label' => 'Nome', 'regra' => 'required|max:255'],
                    'alerta_dias' => ['label' => 'Avisar quantos dias antes de vencer', 'tipo' => 'number', 'regra' => 'required|integer|min:0|max:365', 'padrao' => 30],
                    'obrigatorio' => ['label' => 'Obrigatório no dossiê', 'tipo' => 'checkbox', 'regra' => 'boolean'],
                    'exige_validade' => ['label' => 'Exige data de validade', 'tipo' => 'checkbox', 'regra' => 'boolean'],
                    'colaborador_envia' => ['label' => 'Colaborador pode enviar pelo portal', 'tipo' => 'checkbox', 'regra' => 'boolean', 'padrao' => true],
                ],
            ],
        };
    }

    private function recurso(Request $request): string
    {
        return explode('.', $request->route()->getName())[0];
    }

    public function index(Request $request)
    {
        $recurso = $this->recurso($request);
        $cfg = $this->config($recurso);
        $itens = $cfg['model']::with($cfg['com'] ?? [])->orderBy($cfg['ordem'])->paginate(25);
        return view('cadastros.simples.index', compact('recurso', 'cfg', 'itens'));
    }

    public function create(Request $request)
    {
        $recurso = $this->recurso($request);
        $cfg = $this->config($recurso);
        $item = new $cfg['model'];
        return view('cadastros.simples.form', compact('recurso', 'cfg', 'item'));
    }

    public function edit(Request $request, int $id)
    {
        $recurso = $this->recurso($request);
        $cfg = $this->config($recurso);
        $item = $cfg['model']::findOrFail($id);
        return view('cadastros.simples.form', compact('recurso', 'cfg', 'item'));
    }

    public function store(Request $request)
    {
        return $this->salvar($request, null);
    }

    public function update(Request $request, int $id)
    {
        return $this->salvar($request, $id);
    }

    private function salvar(Request $request, ?int $id)
    {
        $recurso = $this->recurso($request);
        $cfg = $this->config($recurso);
        $regras = [];
        foreach ($cfg['campos'] as $campo => $def) {
            $regras[$campo] = is_callable($def['regra']) ? ($def['regra'])($id) : $def['regra'];
        }
        $dados = $request->validate($regras);
        foreach ($cfg['campos'] as $campo => $def) {
            if (($def['tipo'] ?? null) === 'checkbox') {
                $dados[$campo] = $request->boolean($campo);
            }
        }
        $item = $id ? $cfg['model']::findOrFail($id) : new $cfg['model'];
        $item->fill($dados)->save();

        return redirect()->route("$recurso.index")->with('ok', ucfirst($cfg['singular']).' salvo com sucesso.');
    }

    public function destroy(Request $request, int $id)
    {
        $recurso = $this->recurso($request);
        $cfg = $this->config($recurso);
        try {
            $cfg['model']::findOrFail($id)->delete();
        } catch (QueryException) {
            return back()->with('erro', 'Não é possível excluir: há registros vinculados.');
        }
        return redirect()->route("$recurso.index")->with('ok', 'Registro excluído.');
    }
}
