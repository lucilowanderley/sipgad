<?php

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\Colaborador;
use App\Models\ColaboradorJornada;
use App\Models\Empresa;
use App\Models\Jornada;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ColaboradorController extends Controller
{
    public function index(Request $request)
    {
        $colaboradores = Colaborador::with('setor', 'cargo', 'empresa')
            ->when($request->busca, fn ($q, $b) => $q->where(fn ($q) => $q->where('nome', 'like', "%$b%")->orWhere('matricula', $b)->orWhere('cpf', 'like', "%$b%")))
            ->when($request->setor_id, fn ($q, $s) => $q->where('setor_id', $s))
            ->when($request->situacao !== 'todos', fn ($q) => $q->where('ativo', $request->situacao !== 'inativos'))
            ->orderBy('nome')->paginate(25)->withQueryString();
        $setores = Setor::orderBy('nome')->get();
        return view('cadastros.colaboradores.index', compact('colaboradores', 'setores'));
    }

    public function create()
    {
        return $this->form(new Colaborador(['tipo_contrato' => 'clt', 'ativo' => true, 'data_admissao' => today()]));
    }

    public function edit(Colaborador $colaborador)
    {
        return $this->form($colaborador->load('jornadas.jornada', 'user'));
    }

    private function form(Colaborador $colaborador)
    {
        return view('cadastros.colaboradores.form', [
            'colaborador' => $colaborador,
            'empresas' => Empresa::orderBy('razao_social')->get(),
            'setores' => Setor::orderBy('nome')->get(),
            'cargos' => Cargo::orderBy('nome')->get(),
            'jornadas' => Jornada::orderBy('nome')->get(),
            'gestores' => Colaborador::where('ativo', true)->whereKeyNot($colaborador->id)->orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request)
    {
        return $this->salvar($request, new Colaborador);
    }

    public function update(Request $request, Colaborador $colaborador)
    {
        return $this->salvar($request, $colaborador);
    }

    private function salvar(Request $request, Colaborador $colaborador)
    {
        $request->merge([
            'cpf' => $this->mascararCpf($request->cpf),
            'salario' => $request->filled('salario') ? str_replace(['.', ','], ['', '.'], $request->salario) : null,
        ]);
        $dados = $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'setor_id' => 'nullable|exists:setores,id',
            'cargo_id' => 'nullable|exists:cargos,id',
            'gestor_id' => 'nullable|exists:colaboradores,id',
            'matricula' => ['required', 'max:30', Rule::unique('colaboradores')->where('empresa_id', $request->empresa_id)->ignore($colaborador->id)],
            'nome' => 'required|max:255',
            'cpf' => ['required', 'regex:/^\d{3}\.\d{3}\.\d{3}-\d{2}$/', Rule::unique('colaboradores')->ignore($colaborador->id)],
            'pis' => 'nullable|max:14',
            'data_nascimento' => 'nullable|date',
            'email' => 'nullable|email',
            'telefone' => 'nullable|max:20',
            'data_admissao' => 'required|date',
            'data_demissao' => 'nullable|date|after_or_equal:data_admissao',
            'tipo_contrato' => ['required', Rule::in(array_keys(Colaborador::TIPOS_CONTRATO))],
            'salario' => 'nullable|numeric|min:0',
            'jornada_id' => 'nullable|exists:jornadas,id',
            'jornada_inicio' => 'nullable|date|required_with:jornada_id',
            'data_base_revezamento' => 'nullable|date',
            'acesso_email' => ['nullable', 'email', Rule::unique('users', 'email')->ignore($colaborador->user_id)],
            'acesso_senha' => 'nullable|min:8',
            'acesso_perfil' => ['nullable', Rule::in(array_keys(User::PERFIS))],
        ], [
            'cpf.regex' => 'CPF deve ter 11 dígitos.',
        ]);

        if ($dados['cpf'] && ! $this->cpfValido($dados['cpf'])) {
            return back()->withInput()->withErrors(['cpf' => 'CPF inválido.']);
        }
        if (($dados['acesso_perfil'] ?? null) === 'admin' && ! $request->user()->temPerfil('admin')) {
            return back()->withInput()->withErrors(['acesso_perfil' => 'Só um administrador pode criar outro administrador.']);
        }

        DB::transaction(function () use ($colaborador, $dados) {
            $campos = collect($dados)->only($colaborador->getFillable())->except('user_id')->all();
            $campos['ativo'] = empty($dados['data_demissao']) || $dados['data_demissao'] >= today()->toDateString();
            $colaborador->fill($campos)->save();

            // Nova jornada: encerra a vigente no dia anterior e abre a nova
            if (! empty($dados['jornada_id'])) {
                $atual = $colaborador->jornadas()->whereNull('fim')->first();
                $mudou = ! $atual || $atual->jornada_id != $dados['jornada_id']
                    || $atual->inicio->toDateString() !== $dados['jornada_inicio']
                    || optional($atual->data_base_revezamento)->toDateString() !== ($dados['data_base_revezamento'] ?? null);
                if ($mudou) {
                    if ($atual && $atual->inicio->toDateString() >= $dados['jornada_inicio']) {
                        $atual->delete();
                    } elseif ($atual) {
                        $atual->update(['fim' => \Carbon\Carbon::parse($dados['jornada_inicio'])->subDay()]);
                    }
                    ColaboradorJornada::create([
                        'colaborador_id' => $colaborador->id,
                        'jornada_id' => $dados['jornada_id'],
                        'inicio' => $dados['jornada_inicio'],
                        'data_base_revezamento' => $dados['data_base_revezamento'] ?? null,
                    ]);
                }
            }

            // Acesso ao sistema
            if (! empty($dados['acesso_email'])) {
                $user = $colaborador->user ?? new User;
                $user->fill([
                    'name' => $colaborador->nome,
                    'email' => $dados['acesso_email'],
                    'perfil' => $dados['acesso_perfil'] ?? $user->perfil ?? 'colaborador',
                    'ativo' => $colaborador->ativo,
                ]);
                if (! empty($dados['acesso_senha'])) {
                    $user->password = $dados['acesso_senha'];
                }
                if (! $user->exists && empty($dados['acesso_senha'])) {
                    $user->password = str_replace(['.', '-'], '', $colaborador->cpf); // senha inicial = CPF
                }
                $user->save();
                if ($colaborador->user_id !== $user->id) {
                    $colaborador->forceFill(['user_id' => $user->id])->save();
                }
            } elseif ($colaborador->user) {
                $colaborador->user->update(['ativo' => $colaborador->ativo]);
            }
        });

        return redirect()->route('colaboradores.index')->with('ok', 'Colaborador salvo.');
    }

    private function mascararCpf(?string $cpf): ?string
    {
        $n = preg_replace('/\D/', '', (string) $cpf);
        return strlen($n) === 11 ? substr($n, 0, 3).'.'.substr($n, 3, 3).'.'.substr($n, 6, 3).'-'.substr($n, 9) : $cpf;
    }

    private function cpfValido(string $cpf): bool
    {
        $n = preg_replace('/\D/', '', $cpf);
        if (strlen($n) !== 11 || preg_match('/^(\d)\1{10}$/', $n)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $s = 0;
            for ($i = 0; $i < $t; $i++) {
                $s += $n[$i] * (($t + 1) - $i);
            }
            if ($n[$t] != ((10 * $s) % 11) % 10) {
                return false;
            }
        }
        return true;
    }
}
