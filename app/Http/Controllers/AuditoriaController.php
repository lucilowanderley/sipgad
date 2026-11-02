<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Services\RegistroPonto;
use App\Models\Empresa;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    public function index(Request $request, RegistroPonto $registro)
    {
        $registros = Auditoria::with('user')
            ->when($request->entidade, fn ($q, $e) => $q->where('entidade', $e))
            ->latest('id')->paginate(50)->withQueryString();
        $entidades = Auditoria::distinct()->orderBy('entidade')->pluck('entidade');
        $integridade = $request->boolean('verificar')
            ? Empresa::all()->mapWithKeys(fn ($e) => [$e->nome => $registro->verificarCadeia($e->id)])
            : null;
        return view('auditoria', compact('registros', 'entidades', 'integridade'));
    }
}
