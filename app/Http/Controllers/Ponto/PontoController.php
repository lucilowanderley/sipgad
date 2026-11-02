<?php

namespace App\Http\Controllers\Ponto;

use App\Http\Controllers\Controller;
use App\Models\Marcacao;
use App\Services\RegistroPonto;
use DomainException;
use Illuminate\Http\Request;

class PontoController extends Controller
{
    public function bater(Request $request)
    {
        $colaborador = $request->user()->colaborador;
        $hoje = $colaborador?->marcacoes()->validas()->whereDate('data_hora', today())->orderBy('data_hora')->get() ?? collect();
        return view('ponto.bater', compact('colaborador', 'hoje'));
    }

    public function registrar(Request $request, RegistroPonto $registro)
    {
        $colaborador = $request->user()->colaborador;
        abort_unless($colaborador, 403, 'Seu usuário não está vinculado a um colaborador.');

        try {
            // A hora é sempre a do servidor, nunca a enviada pelo navegador.
            $marcacao = $registro->registrar($colaborador, now(), [
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'criado_por' => $request->user()->id,
            ]);
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect()->route('ponto.comprovante', $marcacao)->with('ok', 'Ponto registrado às '.$marcacao->data_hora->format('H:i').'.');
    }

    public function comprovante(Request $request, Marcacao $marcacao)
    {
        $this->autorizarColaborador($request->user(), $marcacao->colaborador);
        $marcacao->load('colaborador.empresa');
        return view('ponto.comprovante', compact('marcacao'));
    }
}
