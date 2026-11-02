<?php

namespace Tests\Feature;

use App\Models\Colaborador;
use App\Models\ColaboradorJornada;
use App\Models\Comunicado;
use App\Models\Documento;
use App\Models\Empresa;
use App\Models\Feriado;
use App\Models\FeriasProgramacao;
use App\Models\Jornada;
use App\Models\Setor;
use App\Models\Solicitacao;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Services\Apuracao;
use App\Services\Fechamento;
use App\Services\Ferias;
use App\Services\RegistroPonto;
use App\Services\Solicitacoes;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Fase2Test extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private Setor $setor;
    private Jornada $jornada;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 12:00:00'); // terça-feira
        $this->empresa = Empresa::create(['razao_social' => 'Teste Ltda', 'cnpj' => '00.000.000/0001-91']);
        $this->setor = Setor::create(['empresa_id' => $this->empresa->id, 'nome' => 'Operação']);
        $this->jornada = Jornada::create(['empresa_id' => $this->empresa->id, 'nome' => 'Comercial', 'tipo' => 'semanal']);
        foreach ([1, 2, 3, 4, 5] as $d) {
            $this->jornada->dias()->create(['dia_semana' => $d, 'entrada1' => '08:00', 'saida1' => '12:00', 'entrada2' => '13:00', 'saida2' => '17:00']);
        }
    }

    private function pessoa(string $nome, string $perfil = 'colaborador', ?Colaborador $gestor = null, string $admissao = '2026-01-01'): Colaborador
    {
        static $n = 0;
        $n++;
        $user = User::create(['name' => $nome, 'email' => "u{$n}@t.test", 'password' => 'x', 'perfil' => $perfil]);
        $c = Colaborador::create(['empresa_id' => $this->empresa->id, 'setor_id' => $this->setor->id, 'user_id' => $user->id,
            'gestor_id' => $gestor?->id, 'matricula' => (string) $n, 'nome' => $nome, 'cpf' => "000.000.000-{$n}", 'data_admissao' => $admissao]);
        ColaboradorJornada::create(['colaborador_id' => $c->id, 'jornada_id' => $this->jornada->id, 'inicio' => '2026-01-01']);
        return $c->fresh();
    }

    private function dia(Colaborador $c, string $data): array
    {
        return app(Apuracao::class)->periodo($c, Carbon::parse($data), Carbon::parse($data))['dias'][0];
    }

    // ---------- Solicitações e aprovação ----------

    public function test_abono_de_dia_inteiro_elimina_falta_e_debito(): void
    {
        $c = $this->pessoa('Ana');
        $this->assertTrue($this->dia($c, '2026-10-14')['falta']);

        Solicitacao::create(['colaborador_id' => $c->id, 'tipo' => 'atestado', 'data' => '2026-10-14', 'data_fim' => '2026-10-15', 'motivo' => 'gripe', 'status' => 'aprovada']);
        $d = $this->dia($c, '2026-10-14');
        $this->assertFalse($d['falta']);
        $this->assertSame(480, $d['abonado']);
        $this->assertSame(0, $d['saldo']);
        $this->assertSame('Atestado', $d['ocorrencia']);
        $this->assertSame(0, $this->dia($c, '2026-10-15')['debito']);
        $this->assertTrue($this->dia($c, '2026-10-16')['falta']);
    }

    public function test_abono_parcial_cobre_so_as_horas_informadas(): void
    {
        $c = $this->pessoa('Ana');
        foreach (['2026-10-14 10:00', '2026-10-14 12:00', '2026-10-14 13:00', '2026-10-14 17:00'] as $h) {
            app(RegistroPonto::class)->registrar($c, Carbon::parse($h), ['origem' => 'ajuste', 'motivo' => 't']);
        }
        $this->assertSame(120, $this->dia($c, '2026-10-14')['debito']);
        Solicitacao::create(['colaborador_id' => $c->id, 'tipo' => 'abono', 'data' => '2026-10-14', 'minutos' => 90, 'motivo' => 'consulta', 'status' => 'aprovada']);
        $d = $this->dia($c, '2026-10-14');
        $this->assertSame(90, $d['abonado']);
        $this->assertSame(30, $d['debito']);
        $this->assertSame('Abono parcial', $d['ocorrencia']);
    }

    public function test_abono_pendente_ou_rejeitado_nao_conta(): void
    {
        $c = $this->pessoa('Ana');
        Solicitacao::create(['colaborador_id' => $c->id, 'tipo' => 'abono', 'data' => '2026-10-14', 'motivo' => 'x', 'status' => 'pendente']);
        Solicitacao::create(['colaborador_id' => $c->id, 'tipo' => 'abono', 'data' => '2026-10-14', 'motivo' => 'x', 'status' => 'rejeitada']);
        $this->assertTrue($this->dia($c, '2026-10-14')['falta']);
    }

    public function test_fluxo_web_colaborador_pede_ajuste_e_gestor_aprova(): void
    {
        $gestor = $this->pessoa('Gestora', 'gestor');
        $c = $this->pessoa('Bruno', 'colaborador', $gestor);
        $outroGestor = $this->pessoa('Outro', 'gestor');

        $this->actingAs($c->user)->post('/solicitacoes', ['tipo' => 'ajuste', 'data' => '2026-10-19', 'hora' => '17:05', 'motivo' => 'esqueci a saída'])
            ->assertRedirect('/solicitacoes')->assertSessionHas('ok');
        $s = Solicitacao::firstOrFail();
        $this->assertSame('pendente', $s->status);

        // colaborador não acessa a caixa; outro gestor não decide
        $this->actingAs($c->user)->get('/aprovacoes')->assertForbidden();
        $this->actingAs($outroGestor->user)->post("/aprovacoes/solicitacoes/{$s->id}", ['decisao' => 'aprovar'])->assertSessionHas('erro');
        $this->actingAs($outroGestor->user)->get('/aprovacoes')->assertOk()->assertDontSee('esqueci a saída');

        $this->actingAs($gestor->user)->get('/aprovacoes')->assertOk()->assertSee('esqueci a saída');
        $this->actingAs($gestor->user)->post("/aprovacoes/solicitacoes/{$s->id}", ['decisao' => 'aprovar'])->assertSessionHas('ok');

        $s->refresh();
        $this->assertSame('aprovada', $s->status);
        $this->assertNotNull($s->marcacao_id);
        $this->assertSame('ajuste', $s->marcacao->origem);
        $this->assertSame('2026-10-19 17:05:00', $s->marcacao->data_hora->format('Y-m-d H:i:s'));
    }

    public function test_rejeicao_exige_resposta_e_ninguem_aprova_o_proprio_pedido(): void
    {
        $dp = $this->pessoa('DP', 'dp');
        $s = Solicitacao::create(['colaborador_id' => $dp->id, 'tipo' => 'abono', 'data' => '2026-10-14', 'motivo' => 'x', 'criado_por' => $dp->user_id]);
        $this->expectException(DomainException::class);
        app(Solicitacoes::class)->aprovar($s, $dp->user);
    }

    public function test_atestado_exige_anexo_e_gestor_ve_o_anexo(): void
    {
        Storage::fake('local');
        $gestor = $this->pessoa('Gestora', 'gestor');
        $c = $this->pessoa('Bruno', 'colaborador', $gestor);
        $this->actingAs($c->user)->post('/solicitacoes', ['tipo' => 'atestado', 'data' => '2026-10-19', 'motivo' => 'consulta médica'])
            ->assertSessionHasErrors('anexo');
        $this->actingAs($c->user)->post('/solicitacoes', ['tipo' => 'atestado', 'data' => '2026-10-19', 'motivo' => 'consulta médica',
            'anexo' => UploadedFile::fake()->create('atestado.pdf', 100, 'application/pdf')])->assertSessionHas('ok');
        $s = Solicitacao::firstOrFail();
        Storage::disk('local')->assertExists($s->anexo_path);
        $this->actingAs($gestor->user)->get("/solicitacoes/{$s->id}/anexo")->assertOk();
        $this->actingAs($this->pessoa('Estranho')->user)->get("/solicitacoes/{$s->id}/anexo")->assertNotFound();
    }

    public function test_nao_aprova_abono_em_periodo_fechado(): void
    {
        $dp = $this->pessoa('DP', 'dp');
        $c = $this->pessoa('Ana');
        app(Fechamento::class)->fechar($this->empresa, '2026-09', $dp->user_id);
        $s = Solicitacao::create(['colaborador_id' => $c->id, 'tipo' => 'abono', 'data' => '2026-09-10', 'motivo' => 'x']);
        $this->expectExceptionMessage('já está fechada');
        app(Solicitacoes::class)->aprovar($s, $dp->user);
    }

    // ---------- Férias ----------

    public function test_periodos_aquisitivos_e_tabela_de_faltas(): void
    {
        $c = $this->pessoa('Ana', admissao: '2024-03-10');
        $p = app(Ferias::class)->sincronizarPeriodos($c);
        $this->assertCount(3, $p);
        $this->assertSame('2024-03-10', $p[0]->inicio->toDateString());
        $this->assertSame('2025-03-09', $p[0]->fim->toDateString());
        $this->assertSame('2026-03-09', $p[0]->limite_concessivo->toDateString());
        $this->assertFalse($p[2]->completo());
        $this->assertCount(3, app(Ferias::class)->sincronizarPeriodos($c)); // idempotente

        $this->assertSame(30, Ferias::diasPorFaltas(5));
        $this->assertSame(24, Ferias::diasPorFaltas(6));
        $this->assertSame(18, Ferias::diasPorFaltas(23));
        $this->assertSame(12, Ferias::diasPorFaltas(32));
        $this->assertSame(0, Ferias::diasPorFaltas(33));
    }

    public function test_regras_de_fracionamento_venda_e_inicio(): void
    {
        $c = $this->pessoa('Ana', admissao: '2025-01-01');
        $f = app(Ferias::class);
        [$per, $emFormacao] = $f->sincronizarPeriodos($c)->values()->all(); // 2025 (completo) e 2026 (em formação)
        $seg = Carbon::parse('2026-11-30'); // segunda

        $this->assertNotEmpty($f->validar($emFormacao, Carbon::parse('2026-12-14'), 30)['erros'], 'antes de completar o aquisitivo');
        $this->assertEmpty($f->validar($per, $seg, 30)['erros']);
        $this->assertEmpty($f->validar($per, $seg, 20, 10)['erros']);
        $this->assertEmpty($f->validar($per, $seg, 10)['erros'], '10 dias: ainda cabem 20 (um de 14)');
        $this->assertNotEmpty($f->validar($per, $seg, 4)['erros'], 'mínimo 5 dias');
        $this->assertNotEmpty($f->validar($per, $seg, 19, 11)['erros'], 'venda acima de 1/3');
        $this->assertNotEmpty($f->validar($per, $seg, 28)['erros'], 'sobrariam 2 dias');
        $this->assertNotEmpty($f->validar($per, Carbon::parse('2026-12-04'), 20)['erros'], 'sexta: antecede domingo');

        Feriado::create(['data' => '2026-12-09', 'descricao' => 'Feriado local']);
        $this->assertNotEmpty($f->validar($per, Carbon::parse('2026-12-07'), 20)['erros'], 'antecede feriado');

        $avisos = $f->validar($per, Carbon::parse('2026-11-03'), 30)['avisos'];
        $this->assertStringContainsString('30 dias', implode(' ', $avisos), 'aviso de antecedência');
        $this->assertStringContainsString('dobro', implode(' ', $f->validar($per, Carbon::parse('2026-12-14'), 30)['avisos']));
    }

    public function test_tres_periodos_e_saldo(): void
    {
        $c = $this->pessoa('Ana', 'colaborador', null, '2025-01-01');
        $f = app(Ferias::class);
        $per = $f->sincronizarPeriodos($c)->first();
        $f->programar($per, ['inicio' => '2026-02-02', 'dias' => 14], $c->user);
        $f->programar($per, ['inicio' => '2026-04-06', 'dias' => 6], $c->user);
        $this->assertSame(10, $per->fresh()->saldo());
        $this->assertNotEmpty($f->validar($per, Carbon::parse('2026-06-01'), 6)['erros'], '3º período deixaria 4 dias sobrando');
        $f->programar($per, ['inicio' => '2026-06-01', 'dias' => 10], $c->user);
        $this->assertSame(0, $per->fresh()->saldo());
        $this->expectException(DomainException::class);
        $f->programar($per, ['inicio' => '2026-08-03', 'dias' => 5], $c->user);
    }

    public function test_menos_de_14_dias_sem_periodo_principal_e_bloqueado(): void
    {
        $c = $this->pessoa('Ana', admissao: '2025-01-01');
        $f = app(Ferias::class);
        $per = $f->sincronizarPeriodos($c)->first();
        // 10 dias + venda de 10 = sobram 10 (< 14) e nenhum período >= 14
        $r = $f->validar($per, Carbon::parse('2026-02-02'), 10, 10);
        $this->assertStringContainsString('14 dias', implode(' ', $r['erros']));
        // 10 dias sem venda: sobram 20, ainda cabe um de 14
        $this->assertEmpty($f->validar($per, Carbon::parse('2026-02-02'), 10)['erros']);
    }

    public function test_ferias_aprovadas_pelo_gestor_aparecem_no_espelho_sem_falta(): void
    {
        $gestor = $this->pessoa('Gestora', 'gestor');
        $c = $this->pessoa('Bruno', 'colaborador', $gestor, '2025-01-01');
        $per = app(Ferias::class)->sincronizarPeriodos($c)->first();

        $this->actingAs($c->user)->post('/ferias', ['periodo_id' => $per->id, 'inicio' => '2026-11-30', 'dias' => 30])->assertSessionHas('ok');
        $p = FeriasProgramacao::firstOrFail();
        $this->assertSame('pendente', $p->status);
        $this->assertSame('2026-12-29', $p->fim->toDateString());

        $this->actingAs($gestor->user)->post("/aprovacoes/ferias/{$p->id}", ['decisao' => 'aprovar'])->assertSessionHas('ok');
        $this->assertSame('aprovada', $p->fresh()->status);

        Carbon::setTestNow('2027-01-05 12:00:00');
        $d = $this->dia($c->fresh(), '2026-12-01');
        $this->assertTrue($d['ferias']);
        $this->assertFalse($d['falta']);
        $this->assertSame(0, $d['previsto']);
        $this->assertSame('Férias', $d['ocorrencia']);
    }

    public function test_alerta_de_vencimento(): void
    {
        $c = $this->pessoa('Ana', admissao: '2024-01-01'); // 1º concessivo termina 2025-12-31: vencido
        $alertas = app(Ferias::class)->alertas(collect([$c]));
        $this->assertSame('vencido', $alertas->first()->nivel);
        $this->assertSame(30, $alertas->first()->saldo);

        $c2 = $this->pessoa('Bia', admissao: '2025-02-01'); // concessivo até 2027-01-31; iniciar até 2027-01-02 => 74 dias: atenção
        $a2 = app(Ferias::class)->alertas(collect([$c2]))->first();
        $this->assertSame('atencao', $a2->nivel);
        $this->assertSame('2027-01-02', $a2->ultimo_inicio->toDateString());
    }

    public function test_cronograma_e_controle_carregam(): void
    {
        $gestor = $this->pessoa('Gestora', 'gestor');
        $c = $this->pessoa('Bruno', 'colaborador', $gestor, '2025-01-01');
        $per = app(Ferias::class)->sincronizarPeriodos($c)->first();
        app(Ferias::class)->programar($per, ['inicio' => '2026-11-30', 'dias' => 30], $c->user, false);
        $this->actingAs($gestor->user)->get('/ferias/cronograma')->assertOk()->assertSee('Bruno');
        $this->actingAs($gestor->user)->get('/ferias/controle')->assertOk();
        $this->actingAs($c->user)->get('/ferias')->assertOk()->assertSee('Aguardando aprovação');
        $this->actingAs($c->user)->getJson('/ferias/simular?periodo_id='.$per->id.'&inicio=2027-02-05&dias=10')->assertOk()->assertJsonStructure(['erros', 'avisos', 'fim']);
    }

    // ---------- Dossiê ----------

    public function test_dossie_upload_validade_e_acesso(): void
    {
        Storage::fake('local');
        $gestor = $this->pessoa('Gestora', 'gestor');
        $c = $this->pessoa('Bruno', 'colaborador', $gestor);
        $dp = $this->pessoa('DP', 'dp');
        $aso = TipoDocumento::create(['nome' => 'ASO', 'obrigatorio' => true, 'exige_validade' => true, 'alerta_dias' => 30, 'colaborador_envia' => false]);
        $rg = TipoDocumento::create(['nome' => 'RG', 'obrigatorio' => true]);

        $arquivo = fn () => UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf');
        // colaborador envia RG; não pode enviar ASO
        $this->actingAs($c->user)->post('/documentos', ['colaborador_id' => $c->id, 'tipo_documento_id' => $rg->id, 'arquivo' => $arquivo()])->assertSessionHas('ok');
        $this->actingAs($c->user)->post('/documentos', ['colaborador_id' => $c->id, 'tipo_documento_id' => $aso->id, 'validade' => '2027-01-01', 'arquivo' => $arquivo()])->assertForbidden();
        // colaborador não envia para outro
        $this->actingAs($c->user)->post('/documentos', ['colaborador_id' => $dp->id, 'tipo_documento_id' => $rg->id, 'arquivo' => $arquivo()])->assertForbidden();
        // ASO exige validade
        $this->actingAs($dp->user)->post('/documentos', ['colaborador_id' => $c->id, 'tipo_documento_id' => $aso->id, 'arquivo' => $arquivo()])->assertSessionHasErrors('validade');
        $this->actingAs($dp->user)->post('/documentos', ['colaborador_id' => $c->id, 'tipo_documento_id' => $aso->id, 'validade' => '2026-11-05', 'arquivo' => $arquivo()])->assertSessionHas('ok');

        $doc = Documento::where('tipo_documento_id', $aso->id)->firstOrFail();
        $this->assertSame('vencendo', $doc->situacao());
        $this->assertNotNull($doc->conferido_em);
        Storage::disk('local')->assertExists($doc->arquivo_path);

        $this->actingAs($c->user)->get("/documentos/{$doc->id}/arquivo")->assertOk();
        $this->actingAs($gestor->user)->get("/documentos/{$doc->id}/arquivo")->assertForbidden();
        $this->actingAs($gestor->user)->get('/documentos?colaborador_id='.$c->id)->assertForbidden();
        $this->actingAs($dp->user)->get('/documentos/vencimentos')->assertOk()->assertSee('Bruno')->assertSee('Vence em breve');

        // renovação: o novo ASO substitui o antigo e o alerta some
        Carbon::setTestNow('2026-10-21 12:00:00');
        $this->actingAs($dp->user)->post('/documentos', ['colaborador_id' => $c->id, 'tipo_documento_id' => $aso->id, 'validade' => '2027-10-21', 'arquivo' => $arquivo()]);
        $this->actingAs($dp->user)->get('/documentos/vencimentos')->assertOk()->assertDontSee('Vence em breve');
    }

    // ---------- Mural ----------

    public function test_mural_publico_por_setor_agendamento_e_ciencia(): void
    {
        $dp = $this->pessoa('DP', 'dp');
        $c = $this->pessoa('Bruno');
        $outroSetor = Setor::create(['empresa_id' => $this->empresa->id, 'nome' => 'ADM']);

        $this->actingAs($dp->user)->post('/mural', ['titulo' => 'Aviso geral', 'corpo' => 'Texto', 'categoria' => 'geral',
            'publicar_em' => '2026-10-20 08:00', 'exige_ciencia' => 1])->assertRedirect();
        Comunicado::create(['titulo' => 'Só ADM', 'corpo' => 'x', 'setor_id' => $outroSetor->id, 'empresa_id' => $this->empresa->id, 'publicar_em' => now()->subHour()]);
        Comunicado::create(['titulo' => 'Agendado', 'corpo' => 'x', 'publicar_em' => now()->addDay()]);
        Comunicado::create(['titulo' => 'Expirado', 'corpo' => 'x', 'publicar_em' => now()->subDays(10), 'expira_em' => today()->subDay()]);

        $this->actingAs($c->user)->get('/mural')->assertOk()->assertSee('Aviso geral')->assertDontSee('Só ADM')->assertDontSee('Agendado')->assertDontSee('Expirado');
        $this->actingAs($c->user)->get('/mural/novo')->assertForbidden();

        $aviso = Comunicado::where('titulo', 'Aviso geral')->firstOrFail();
        $adm = Comunicado::where('titulo', 'Só ADM')->firstOrFail();
        $this->actingAs($c->user)->get("/mural/{$adm->id}")->assertNotFound();
        $this->assertSame(1, Comunicado::visiveisPara($c->user)->naoLidosPor($c->user)->count());
        $this->actingAs($c->user)->get("/mural/{$aviso->id}")->assertOk()->assertSee('Li e estou ciente');
        $this->assertSame(0, Comunicado::visiveisPara($c->user)->naoLidosPor($c->user)->count());
        $this->actingAs($c->user)->post("/mural/{$aviso->id}/ciencia")->assertSessionHas('ok');
        $this->assertNotNull($aviso->leituras()->where('user_id', $c->user_id)->value('ciente_em'));

        $this->actingAs($dp->user)->get("/mural/{$aviso->id}/leituras")->assertOk()->assertSee('Bruno');
    }

    public function test_paginas_do_portal_abrem_para_cada_perfil(): void
    {
        $gestor = $this->pessoa('Gestora', 'gestor');
        $c = $this->pessoa('Bruno', 'colaborador', $gestor, '2024-05-01');
        $dp = $this->pessoa('DP', 'dp');
        TipoDocumento::create(['nome' => 'RG', 'obrigatorio' => true]);
        foreach ([$c, $gestor, $dp] as $p) {
            foreach (['/', '/mural', '/solicitacoes', '/ferias', '/ferias/cronograma', '/documentos', '/espelho'] as $url) {
                $this->actingAs($p->user)->get($url)->assertOk();
            }
        }
        foreach (['/aprovacoes', '/ferias/controle', '/documentos/vencimentos', '/tipos-documento', '/mural/novo'] as $url) {
            $this->actingAs($dp->user)->get($url)->assertOk();
        }
    }
}
