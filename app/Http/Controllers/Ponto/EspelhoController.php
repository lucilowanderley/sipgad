<?php

namespace App\Http\Controllers\Ponto;

use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\PeriodoFechamento;
use App\Services\Apuracao;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EspelhoController extends Controller
{
    private function competencia(Request $request): Carbon
    {
        $c = $request->input('competencia', now()->format('Y-m'));
        return preg_match('/^\d{4}-\d{2}$/', $c) ? Carbon::createFromFormat('Y-m-d', "$c-01")->startOfDay() : now()->startOfMonth();
    }

    public function index(Request $request, Apuracao $apuracao)
    {
        $user = $request->user();
        $inicio = $this->competencia($request);
        $fim = $inicio->copy()->endOfMonth()->startOfDay();
        $visiveis = $this->colaboradoresVisiveis($user)->orderBy('nome')->get();

        $colaborador = $request->filled('colaborador_id')
            ? $visiveis->firstWhere('id', (int) $request->colaborador_id)
            : ($user->colaborador ?? $visiveis->first());
        abort_if($request->filled('colaborador_id') && ! $colaborador, 403);

        $resultado = $colaborador ? $apuracao->periodo($colaborador, $inicio, $fim) : null;
        $fechado = $colaborador ? PeriodoFechamento::where('empresa_id', $colaborador->empresa_id)->where('competencia', $inicio->format('Y-m'))->first() : null;
        $desconsideradas = $colaborador
            ? $colaborador->marcacoes()->whereNotNull('desconsiderada_em')->whereBetween('data_hora', [$inicio, $fim->copy()->endOfDay()])
                ->orderBy('data_hora')->get()->groupBy(fn ($m) => $m->data_hora->toDateString())
            : collect();
        $view = $request->boolean('imprimir') ? 'ponto.espelho-impressao' : 'ponto.espelho';

        return view($view, [
            'colaboradores' => $visiveis, 'colaborador' => $colaborador?->load('empresa', 'cargo', 'setor'),
            'inicio' => $inicio, 'fim' => $fim, 'resultado' => $resultado, 'fechado' => $fechado,
            'saldoBanco' => $colaborador?->saldoBancoMinutos(), 'desconsideradas' => $desconsideradas,
        ]);
    }

    /** Resumo da competência de todos os colaboradores visíveis, em CSV (abre no Excel). */
    public function exportar(Request $request, Apuracao $apuracao)
    {
        $inicio = $this->competencia($request);
        $fim = $inicio->copy()->endOfMonth()->startOfDay();
        $colaboradores = $this->colaboradoresVisiveis($request->user())
            ->where('data_admissao', '<=', $fim)
            ->where(fn ($q) => $q->whereNull('data_demissao')->orWhere('data_demissao', '>=', $inicio))
            ->with('setor')->orderBy('nome')->get();

        $nome = 'resumo-ponto-'.$inicio->format('Y-m').'.csv';
        return response()->streamDownload(function () use ($colaboradores, $apuracao, $inicio, $fim) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM para o Excel reconhecer UTF-8
            fputcsv($out, ['Matrícula', 'Nome', 'CPF', 'Setor', 'Previsto', 'Trabalhado', 'Horas extras', 'Débito', 'Saldo', 'Noturno (real)', 'Noturno (reduzida)', 'Abonado', 'Faltas', 'Dias com marcação ímpar', 'Dias de férias'], ';');
            foreach ($colaboradores as $c) {
                $t = $apuracao->periodo($c, $inicio, $fim)['totais'];
                fputcsv($out, [
                    $c->matricula, $c->nome, $c->cpf, $c->setor?->nome,
                    Apuracao::hhmm($t['previsto']), Apuracao::hhmm($t['trabalhado']), Apuracao::hhmm($t['extras']),
                    Apuracao::hhmm($t['debito']), Apuracao::hhmm($t['saldo']), Apuracao::hhmm($t['noturno']),
                    Apuracao::hhmm(Apuracao::horaNoturnaReduzida($t['noturno'])), Apuracao::hhmm($t['abonado']), $t['faltas'], $t['incompletos'], $t['dias_ferias'],
                ], ';');
            }
            fclose($out);
        }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
