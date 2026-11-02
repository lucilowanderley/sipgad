<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Cadastros\CadastroSimplesController;
use App\Http\Controllers\Cadastros\ColaboradorController;
use App\Http\Controllers\Cadastros\JornadaController;
use App\Http\Controllers\PainelController;
use App\Http\Controllers\Ponto\AjusteController;
use App\Http\Controllers\Ponto\BancoHorasController;
use App\Http\Controllers\Ponto\EspelhoController;
use App\Http\Controllers\Ponto\FechamentoController;
use App\Http\Controllers\Ponto\PontoController;
use App\Http\Controllers\Portal\AprovacaoController;
use App\Http\Controllers\Portal\ComunicadoController;
use App\Http\Controllers\Portal\DocumentoController;
use App\Http\Controllers\Portal\FeriasController;
use App\Http\Controllers\Portal\SolicitacaoController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'form'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', PainelController::class)->name('painel');

    // Ponto: qualquer usuário vinculado a um colaborador
    Route::get('/ponto', [PontoController::class, 'bater'])->name('ponto.bater');
    Route::post('/ponto', [PontoController::class, 'registrar'])->name('ponto.registrar')->middleware('throttle:6,1');
    Route::get('/ponto/comprovante/{marcacao}', [PontoController::class, 'comprovante'])->name('ponto.comprovante');
    Route::get('/espelho', [EspelhoController::class, 'index'])->name('espelho');
    Route::get('/banco-horas', [BancoHorasController::class, 'index'])->name('banco-horas.index');

    // Portal do colaborador
    Route::get('/solicitacoes', [SolicitacaoController::class, 'index'])->name('solicitacoes.index');
    Route::post('/solicitacoes', [SolicitacaoController::class, 'store'])->name('solicitacoes.store')->middleware('throttle:20,1');
    Route::post('/solicitacoes/{solicitacao}/cancelar', [SolicitacaoController::class, 'cancelar'])->name('solicitacoes.cancelar');
    Route::get('/solicitacoes/{solicitacao}/anexo', [SolicitacaoController::class, 'anexo'])->name('solicitacoes.anexo');

    Route::get('/ferias', [FeriasController::class, 'index'])->name('ferias.index');
    Route::post('/ferias', [FeriasController::class, 'store'])->name('ferias.store');
    Route::get('/ferias/simular', [FeriasController::class, 'simular'])->name('ferias.simular');
    Route::post('/ferias/{programacao}/cancelar', [FeriasController::class, 'cancelar'])->name('ferias.cancelar');
    Route::get('/ferias/cronograma', [FeriasController::class, 'cronograma'])->name('ferias.cronograma');

    Route::get('/documentos', [DocumentoController::class, 'index'])->name('documentos.index');
    Route::post('/documentos', [DocumentoController::class, 'store'])->name('documentos.store')->middleware('throttle:30,1');
    Route::get('/documentos/{documento}/arquivo', [DocumentoController::class, 'baixar'])->name('documentos.baixar');

    Route::get('/mural', [ComunicadoController::class, 'index'])->name('mural.index');
    Route::get('/mural/{comunicado}', [ComunicadoController::class, 'show'])->name('mural.show')->whereNumber('comunicado');
    Route::post('/mural/{comunicado}/ciencia', [ComunicadoController::class, 'ciencia'])->name('mural.ciencia');

    Route::middleware('perfil:admin,rh,dp,gestor')->group(function () {
        Route::get('/espelho/exportar', [EspelhoController::class, 'exportar'])->name('espelho.exportar');
        Route::get('/aprovacoes', [AprovacaoController::class, 'index'])->name('aprovacoes.index');
        Route::post('/aprovacoes/solicitacoes/{solicitacao}', [AprovacaoController::class, 'solicitacao'])->name('aprovacoes.solicitacao');
        Route::post('/aprovacoes/ferias/{programacao}', [AprovacaoController::class, 'ferias'])->name('aprovacoes.ferias');
        Route::get('/ferias/controle', [FeriasController::class, 'controle'])->name('ferias.controle');
    });

    // Gestão de pessoas
    Route::middleware('perfil:admin,rh,dp')->group(function () {
        foreach (['empresas', 'setores', 'cargos', 'feriados'] as $recurso) {
            Route::resource($recurso, CadastroSimplesController::class)->except('show')->parameters([$recurso => 'id']);
        }
        Route::resource('jornadas', JornadaController::class)->except('show');
        Route::resource('colaboradores', ColaboradorController::class)->except('show')->parameters(['colaboradores' => 'colaborador']);
        Route::resource('tipos-documento', CadastroSimplesController::class)->except('show')->parameters(['tipos-documento' => 'id']);

        Route::put('/ferias/periodos/{periodo}', [FeriasController::class, 'periodo'])->name('ferias.periodo');
        Route::get('/documentos/vencimentos', [DocumentoController::class, 'vencimentos'])->name('documentos.vencimentos');
        Route::post('/documentos/{documento}/conferir', [DocumentoController::class, 'conferir'])->name('documentos.conferir');
        Route::delete('/documentos/{documento}', [DocumentoController::class, 'destroy'])->name('documentos.destroy');

        Route::get('/mural/novo', [ComunicadoController::class, 'create'])->name('mural.create');
        Route::post('/mural', [ComunicadoController::class, 'store'])->name('mural.store');
        Route::get('/mural/{comunicado}/editar', [ComunicadoController::class, 'edit'])->name('mural.edit');
        Route::put('/mural/{comunicado}', [ComunicadoController::class, 'update'])->name('mural.update');
        Route::delete('/mural/{comunicado}', [ComunicadoController::class, 'destroy'])->name('mural.destroy');
        Route::get('/mural/{comunicado}/leituras', [ComunicadoController::class, 'leituras'])->name('mural.leituras');
    });

    // Departamento Pessoal: ajustes, banco de horas e fechamento
    Route::middleware('perfil:admin,dp')->group(function () {
        Route::post('/ajustes', [AjusteController::class, 'incluir'])->name('ajustes.incluir');
        Route::post('/ajustes/{marcacao}/desconsiderar', [AjusteController::class, 'desconsiderar'])->name('ajustes.desconsiderar');
        Route::post('/banco-horas', [BancoHorasController::class, 'lancar'])->name('banco-horas.lancar');
        Route::get('/fechamentos', [FechamentoController::class, 'index'])->name('fechamentos.index');
        Route::post('/fechamentos', [FechamentoController::class, 'fechar'])->name('fechamentos.fechar');
    });

    Route::middleware('perfil:admin')->group(function () {
        Route::delete('/fechamentos/{periodo}', [FechamentoController::class, 'reabrir'])->name('fechamentos.reabrir');
        Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria');
    });
});
