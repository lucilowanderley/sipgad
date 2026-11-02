<?php

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Jornada;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JornadaController extends Controller
{
    public function index()
    {
        $jornadas = Jornada::with('dias', 'empresa')->orderBy('nome')->get();
        return view('cadastros.jornadas.index', compact('jornadas'));
    }

    public function create()
    {
        return $this->form(new Jornada(['tipo' => 'semanal']));
    }

    public function edit(Jornada $jornada)
    {
        return $this->form($jornada->load('dias'));
    }

    private function form(Jornada $jornada)
    {
        $empresas = Empresa::orderBy('razao_social')->get();
        return view('cadastros.jornadas.form', compact('jornada', 'empresas'));
    }

    public function store(Request $request)
    {
        return $this->salvar($request, new Jornada);
    }

    public function update(Request $request, Jornada $jornada)
    {
        return $this->salvar($request, $jornada);
    }

    private function salvar(Request $request, Jornada $jornada)
    {
        $dados = $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'nome' => 'required|max:255',
            'tipo' => 'required|in:semanal,revezamento',
            'horas_trabalho' => 'required_if:tipo,revezamento|nullable|integer|min:1|max:24',
            'horas_folga' => 'required_if:tipo,revezamento|nullable|integer|min:1|max:72',
            'entrada' => 'required_if:tipo,revezamento|nullable|date_format:H:i',
            'intervalo_minutos' => 'nullable|integer|min:0|max:240',
            'dias' => 'array',
            'dias.*.entrada1' => 'nullable|date_format:H:i',
            'dias.*.saida1' => 'nullable|date_format:H:i|required_with:dias.*.entrada1',
            'dias.*.entrada2' => 'nullable|date_format:H:i',
            'dias.*.saida2' => 'nullable|date_format:H:i|required_with:dias.*.entrada2',
        ]);
        $dados['intervalo_minutos'] = (int) ($dados['intervalo_minutos'] ?? 0);

        DB::transaction(function () use ($jornada, $dados) {
            $jornada->fill(collect($dados)->except('dias')->all())->save();
            $jornada->dias()->delete();
            if ($jornada->tipo === 'semanal') {
                foreach ($dados['dias'] ?? [] as $dia => $h) {
                    if (! empty($h['entrada1'])) {
                        $jornada->dias()->create(['dia_semana' => (int) $dia] + array_filter($h));
                    }
                }
            }
        });

        return redirect()->route('jornadas.index')->with('ok', 'Jornada salva.');
    }

    public function destroy(Jornada $jornada)
    {
        try {
            $jornada->delete();
        } catch (QueryException) {
            return back()->with('erro', 'Jornada em uso por colaboradores; não pode ser excluída.');
        }
        return redirect()->route('jornadas.index')->with('ok', 'Jornada excluída.');
    }
}
