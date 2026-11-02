<?php

namespace Tests\Feature;

use App\Models\Colaborador;
use App\Models\ColaboradorJornada;
use App\Models\Empresa;
use App\Models\Feriado;
use App\Models\Jornada;
use App\Models\Marcacao;
use App\Models\User;
use App\Services\Apuracao;
use App\Services\Fechamento;
use App\Services\RegistroPonto;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class PontoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 12:00:00');
        $this->empresa = Empresa::create(['razao_social' => 'Teste Ltda', 'cnpj' => '00.000.000/0001-91']);
    }

    private function comercial(): Jornada
    {
        $j = Jornada::create(['empresa_id' => $this->empresa->id, 'nome' => 'Comercial', 'tipo' => 'semanal']);
        foreach ([1, 2, 3, 4, 5] as $d) {
            $j->dias()->create(['dia_semana' => $d, 'entrada1' => '08:00', 'saida1' => '12:00', 'entrada2' => '13:00', 'saida2' => '17:00']);
        }
        return $j;
    }

    private function colaborador(Jornada $jornada, string $cpf = '529.982.247-25', ?string $base = null): Colaborador
    {
        $c = Colaborador::create(['empresa_id' => $this->empresa->id, 'matricula' => $cpf, 'nome' => 'Fulano', 'cpf' => $cpf, 'data_admissao' => '2026-01-01']);
        ColaboradorJornada::create(['colaborador_id' => $c->id, 'jornada_id' => $jornada->id, 'inicio' => '2026-01-01', 'data_base_revezamento' => $base]);
        return $c->fresh();
    }

    private function bater(Colaborador $c, string ...$horarios): void
    {
        foreach ($horarios as $h) {
            app(RegistroPonto::class)->registrar($c, Carbon::parse($h), ['origem' => 'ajuste', 'motivo' => 'teste']);
        }
    }

    private function dia(Colaborador $c, string $data): array
    {
        return app(Apuracao::class)->periodo($c, Carbon::parse($data), Carbon::parse($data))['dias'][0];
    }

    public function test_dia_normal_dentro_da_tolerancia_nao_gera_saldo(): void
    {
        $c = $this->colaborador($this->comercial());
        $this->bater($c, '2026-10-14 07:57', '2026-10-14 12:01', '2026-10-14 13:02', '2026-10-14 17:06');
        $d = $this->dia($c, '2026-10-14');
        $this->assertSame(480, $d['previsto']);
        $this->assertSame(488, $d['trabalhado']);
        $this->assertSame(0, $d['saldo']);
    }

    public function test_hora_extra_e_debito(): void
    {
        $c = $this->colaborador($this->comercial());
        $this->bater($c, '2026-10-14 08:00', '2026-10-14 12:00', '2026-10-14 13:00', '2026-10-14 18:30');
        $this->assertSame(90, $this->dia($c, '2026-10-14')['extras']);

        $this->bater($c, '2026-10-15 08:00', '2026-10-15 12:00', '2026-10-15 13:00', '2026-10-15 16:00');
        $this->assertSame(60, $this->dia($c, '2026-10-15')['debito']);
    }

    public function test_falta_folga_feriado_e_marcacao_impar(): void
    {
        $c = $this->colaborador($this->comercial());
        $this->assertTrue($this->dia($c, '2026-10-14')['falta']);
        $this->assertSame('Folga', $this->dia($c, '2026-10-18')['ocorrencia']); // domingo

        Feriado::create(['data' => '2026-10-12', 'descricao' => 'Aparecida']);
        $this->bater($c, '2026-10-12 08:00', '2026-10-12 12:00');
        $feriado = $this->dia($c, '2026-10-12');
        $this->assertSame(0, $feriado['previsto']);
        $this->assertSame(240, $feriado['extras']);

        $this->bater($c, '2026-10-16 08:00', '2026-10-16 12:00', '2026-10-16 13:00');
        $this->assertTrue($this->dia($c, '2026-10-16')['incompleto']);
    }

    public function test_dia_futuro_nao_gera_falta(): void
    {
        $c = $this->colaborador($this->comercial());
        $d = $this->dia($c, '2026-10-22');
        $this->assertFalse($d['falta']);
        $this->assertSame(0, $d['debito']);
    }

    public function test_turno_noturno_12x36_atravessa_meia_noite(): void
    {
        $j = Jornada::create(['empresa_id' => $this->empresa->id, 'nome' => '12x36', 'tipo' => 'revezamento',
            'horas_trabalho' => 12, 'horas_folga' => 36, 'entrada' => '19:00', 'intervalo_minutos' => 60]);
        $c = $this->colaborador($j, '111.444.777-35', '2026-10-01');

        $this->assertSame(660, $this->dia($c, '2026-10-13')['previsto']); // 12 dias após a base: trabalha
        $this->assertSame(0, $this->dia($c, '2026-10-14')['previsto']);   // folga

        $this->bater($c, '2026-10-13 19:00', '2026-10-14 00:30', '2026-10-14 01:30', '2026-10-14 07:00');
        $d = $this->dia($c, '2026-10-13');
        $this->assertCount(4, $d['marcacoes']);
        $this->assertSame(660, $d['trabalhado']);
        $this->assertSame(150 + 210, $d['noturno']); // 22:00-00:30 + 01:30-05:00
        $this->assertCount(0, $this->dia($c, '2026-10-14')['marcacoes']);
        $this->assertSame(411, Apuracao::horaNoturnaReduzida(360));
    }

    public function test_nsr_sequencial_hash_encadeado_e_marcacao_imutavel(): void
    {
        $c = $this->colaborador($this->comercial());
        $this->bater($c, '2026-10-14 08:00', '2026-10-14 12:00');
        $this->assertSame([1, 2], Marcacao::orderBy('nsr')->pluck('nsr')->all());
        $this->assertNull(app(RegistroPonto::class)->verificarCadeia($this->empresa->id));

        // Adulteração direta no banco é detectada
        Marcacao::whereKey(1)->toBase()->update(['data_hora' => '2026-10-14 07:00:00']);
        $this->assertSame(1, app(RegistroPonto::class)->verificarCadeia($this->empresa->id));

        $m = Marcacao::find(2);
        $this->expectException(LogicException::class);
        $m->update(['data_hora' => now()]);
    }

    public function test_marcacao_nao_pode_ser_excluida(): void
    {
        $c = $this->colaborador($this->comercial());
        $this->bater($c, '2026-10-14 08:00');
        $this->expectException(LogicException::class);
        Marcacao::first()->delete();
    }

    public function test_fechamento_lanca_banco_e_bloqueia_periodo(): void
    {
        Carbon::setTestNow('2026-11-05 10:00:00');
        $c = $this->colaborador($this->comercial());
        $this->bater($c, '2026-10-14 08:00', '2026-10-14 12:00', '2026-10-14 13:00', '2026-10-14 19:00');

        $saldoMes = app(Apuracao::class)->periodo($c, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'))['totais']['saldo'];
        $periodo = app(Fechamento::class)->fechar($this->empresa, '2026-10');
        $this->assertSame($saldoMes, $c->saldoBancoMinutos());

        try {
            $this->bater($c, '2026-10-20 08:00');
            $this->fail('Deveria bloquear período fechado');
        } catch (DomainException) {
        }

        app(Fechamento::class)->reabrir($periodo);
        $this->assertSame(0, $c->saldoBancoMinutos());
    }

    public function test_colaborador_bate_ponto_pela_web_e_nao_ve_outros(): void
    {
        $user = User::create(['name' => 'F', 'email' => 'f@t.com', 'password' => 'x', 'perfil' => 'colaborador']);
        $c = $this->colaborador($this->comercial());
        $c->update(['user_id' => $user->id]);
        $outro = $this->colaborador($this->comercial(), '111.444.777-35');

        $this->actingAs($user)->post('/ponto')->assertRedirect();
        $this->assertSame(1, $c->marcacoes()->count());
        $this->assertSame('web', $c->marcacoes()->first()->origem);

        // Clique duplo não gera segunda marcação
        $this->actingAs($user)->post('/ponto')->assertSessionHas('erro');
        $this->assertSame(1, $c->marcacoes()->count());

        $this->actingAs($user)->get('/espelho?colaborador_id='.$outro->id)->assertForbidden();
        $this->actingAs($user)->get('/colaboradores')->assertForbidden();
        $this->actingAs($user)->get('/espelho')->assertOk();
    }

    public function test_dp_cadastra_colaborador_com_login(): void
    {
        $dp = User::create(['name' => 'DP', 'email' => 'dp@t.com', 'password' => 'x', 'perfil' => 'dp']);
        $j = $this->comercial();
        $this->actingAs($dp)->post('/colaboradores', [
            'empresa_id' => $this->empresa->id, 'matricula' => '10', 'nome' => 'Beltrano', 'cpf' => '39053344705',
            'data_admissao' => '2026-10-01', 'tipo_contrato' => 'clt', 'jornada_id' => $j->id, 'jornada_inicio' => '2026-10-01',
            'acesso_email' => 'beltrano@t.com', 'acesso_perfil' => 'colaborador',
        ])->assertRedirect('/colaboradores');

        $c = Colaborador::where('matricula', '10')->first();
        $this->assertSame('390.533.447-05', $c->cpf);
        $this->assertSame($j->id, $c->jornadaAtual()->jornada_id);
        $this->assertTrue(auth()->attempt(['email' => 'beltrano@t.com', 'password' => '39053344705']));

        $this->actingAs($dp)->post('/colaboradores', ['empresa_id' => $this->empresa->id, 'matricula' => '11', 'nome' => 'X',
            'cpf' => '12345678900', 'data_admissao' => '2026-10-01', 'tipo_contrato' => 'clt'])->assertSessionHasErrors('cpf');
    }
}
