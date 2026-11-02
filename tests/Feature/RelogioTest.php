<?php

namespace Tests\Feature;

use App\Models\Colaborador;
use App\Models\Empresa;
use App\Models\ImportacaoAfd;
use App\Models\Marcacao;
use App\Models\PeriodoFechamento;
use App\Models\Relogio;
use App\Models\User;
use App\Services\Afd\ImportadorAfd;
use App\Services\Afd\LeitorAfd;
use App\Services\RegistroPonto;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RelogioTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private Relogio $relogio;
    private Colaborador $ana;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Sao_Paulo']);
        date_default_timezone_set('America/Sao_Paulo');
        Storage::fake('local');
        Carbon::setTestNow('2026-11-02 12:00:00');
        $this->empresa = Empresa::create(['razao_social' => 'Teste Ltda', 'cnpj' => '11.222.333/0001-81']);
        $this->relogio = Relogio::create(['empresa_id' => $this->empresa->id, 'nome' => 'Portaria', 'modelo' => 'SMART 671 S']);
        $this->ana = Colaborador::create(['empresa_id' => $this->empresa->id, 'matricula' => '1', 'nome' => 'Ana', 'cpf' => '529.982.247-25',
            'pis' => '123.45678.90-1', 'data_admissao' => '2026-01-01']);
    }

    /** Monta um AFD no leiaute da Portaria 671 (REP-C). */
    private function afd671(array $marcacoes, string $numeroRep = '00004700781000218'): string
    {
        $cab = '000000000'.'1'.'1'.'11222333000181'.str_repeat('0', 14).str_pad('TESTE LTDA', 150)
            .$numeroRep.'2026-10-01'.'2026-11-02'.'2026-11-02T12:05:00-0300'.'003'.'1'.'61092585002256'
            .str_pad('SMART 671 S', 30).'ABCD';
        $linhas = [$cab];
        foreach ($marcacoes as [$nsr, $dh, $cpf]) {
            $linhas[] = str_pad($nsr, 9, '0', STR_PAD_LEFT).'3'.$dh.str_pad($cpf, 12, '0', STR_PAD_LEFT).'1A2B';
        }
        $linhas[] = '999999999'.str_repeat('0', 9).str_pad(count($marcacoes), 9, '0', STR_PAD_LEFT).str_repeat('0', 36).'9';
        $linhas[] = str_pad('ASSINATURA_DIGITAL_EM_ARQUIVO_P7S', 100);

        return implode("\r\n", $linhas)."\r\n";
    }

    private function importar(string $conteudo): ImportacaoAfd
    {
        return app(ImportadorAfd::class)->importar($this->relogio->fresh(), $conteudo, 'AFD.txt');
    }

    public function test_cabecalho_e_marcacoes_do_leiaute_671(): void
    {
        $r = app(LeitorAfd::class)->ler($this->afd671([[11, '2026-11-02T08:01:00-0300', '52998224725']]));

        $this->assertSame('671', $r->leiaute);
        $this->assertSame('00004700781000218', $r->cabecalho['numero_rep']);
        $this->assertSame('SMART 671 S', $r->cabecalho['modelo']);
        $this->assertSame('11222333000181', $r->cabecalho['empregador']);
        $this->assertSame('52998224725', $r->marcacoes[0]['cpf']);
        $this->assertSame('2026-11-02 08:01', $r->marcacoes[0]['data_hora']->format('Y-m-d H:i'));
        $this->assertSame([], $r->erros);
    }

    public function test_importa_por_cpf_e_lista_pendencias(): void
    {
        $imp = $this->importar($this->afd671([
            [10, '2026-11-02T08:01:00-0300', '52998224725'],
            [11, '2026-11-02T08:02:00-0300', '11144477735'], // não cadastrado
            [12, '2026-11-02T12:00:00-0300', '52998224725'],
            [13, '2026-11-02T13:00:00-0200', '52998224725'], // outro fuso: 12:00 em Brasília
        ]));

        $this->assertSame(4, $imp->marcacoes_arquivo);
        $this->assertSame(3, $imp->importadas);
        $this->assertSame(1, $imp->pendentes);
        $this->assertSame('671', $imp->leiaute);
        $this->assertStringContainsString('111.444.777-35', $imp->pendencias[0]['identificador']);
        Storage::disk('local')->assertExists($imp->arquivo_path);

        $m = Marcacao::where('nsr_relogio', 10)->first();
        $this->assertSame('relogio', $m->origem);
        $this->assertSame($this->relogio->id, $m->relogio_id);
        $this->assertSame($this->ana->id, $m->colaborador_id);
        $this->assertSame('2026-11-02 08:01', $m->data_hora->format('Y-m-d H:i'));
        $this->assertSame('2026-11-02 12:00', Marcacao::where('nsr_relogio', 13)->value('data_hora')->format('Y-m-d H:i'));

        $relogio = $this->relogio->fresh();
        $this->assertSame(13, (int) $relogio->ultimo_nsr);
        $this->assertSame('00004700781000218', $relogio->numero_fabricacao);
        $this->assertNull(app(RegistroPonto::class)->verificarCadeia($this->empresa->id));
    }

    public function test_reimportar_nao_duplica_e_reprocessar_resolve_pendencia(): void
    {
        $afd = $this->afd671([[10, '2026-11-02T08:01:00-0300', '52998224725'], [11, '2026-11-02T08:02:00-0300', '11144477735']]);
        $primeira = $this->importar($afd);

        $segunda = $this->importar($afd);
        $this->assertSame(0, $segunda->importadas);
        $this->assertSame(1, $segunda->duplicadas);
        $this->assertSame(1, Marcacao::count());

        Colaborador::create(['empresa_id' => $this->empresa->id, 'matricula' => '2', 'nome' => 'Bia', 'cpf' => '111.444.777-35', 'data_admissao' => '2026-01-01']);
        $terceira = app(ImportadorAfd::class)->reprocessar($primeira);
        $this->assertSame(1, $terceira->importadas);
        $this->assertSame(0, $terceira->pendentes);
        $this->assertSame(2, Marcacao::count());
    }

    public function test_leiaute_1510_por_pis(): void
    {
        $cab = '000000000'.'1'.'1'.'11222333000181'.str_repeat('0', 12).str_pad('TESTE LTDA', 150).'00004700781000218'.'01102026'.'02112026'.'021120261205';
        $afd = implode("\n", [$cab, '000000021'.'3'.'02112026'.'0759'.'012345678901', '999999999'.str_repeat('0', 9).'000000001'.str_repeat('0', 27).'9']);

        $imp = $this->importar($afd);

        $this->assertSame('1510', $imp->leiaute);
        $this->assertSame(1, $imp->importadas);
        $this->assertSame('2026-11-02 07:59', Marcacao::first()->data_hora->format('Y-m-d H:i'));
    }

    public function test_recusa_afd_de_outro_relogio(): void
    {
        $this->relogio->update(['numero_fabricacao' => '00004700781000218']);
        $this->expectException(DomainException::class);
        $this->importar($this->afd671([[1, '2026-11-02T08:00:00-0300', '52998224725']], '99999999999999999'));
    }

    public function test_competencia_fechada_vira_pendencia_e_demitido_vale_ate_a_demissao(): void
    {
        PeriodoFechamento::create(['empresa_id' => $this->empresa->id, 'competencia' => '2026-10', 'inicio' => '2026-10-01', 'fim' => '2026-10-31', 'fechado_em' => now()]);
        $this->ana->update(['ativo' => false, 'data_demissao' => '2026-11-02']);

        $imp = $this->importar($this->afd671([
            [1, '2026-10-30T08:00:00-0300', '52998224725'],
            [2, '2026-11-02T08:00:00-0300', '52998224725'],
        ]));

        $this->assertSame(1, $imp->importadas);
        $this->assertSame(1, $imp->pendentes);
        $this->assertStringContainsString('fechado', $imp->pendencias[0]['motivo']);
    }

    public function test_arquivo_invalido(): void
    {
        $this->expectException(DomainException::class);
        $this->importar("isto não é um AFD\nnem de longe");
    }

    public function test_telas_e_upload_pelo_dp(): void
    {
        $dp = User::create(['name' => 'DP', 'email' => 'dp@t.test', 'password' => 'x', 'perfil' => 'dp']);
        $colab = User::create(['name' => 'C', 'email' => 'c@t.test', 'password' => 'x', 'perfil' => 'colaborador']);

        $this->actingAs($colab)->get('/relogios')->assertForbidden();
        $this->actingAs($dp)->get('/relogios')->assertOk()->assertSee('Portaria');
        $this->actingAs($dp)->get('/relogios/create')->assertOk();
        $this->actingAs($dp)->post('/relogios', ['empresa_id' => $this->empresa->id, 'nome' => 'Fábrica', 'fabricante' => 'Dimep', 'tipo_rep' => 'C', 'ip' => '192.168.0.50', 'porta' => 80, 'ativo' => 1])
            ->assertRedirect();
        $this->assertDatabaseHas('relogios', ['nome' => 'Fábrica', 'ip' => '192.168.0.50']);

        $arquivo = UploadedFile::fake()->createWithContent('AFD00004700781000218.txt', $this->afd671([[5, '2026-11-02T08:00:00-0300', '52998224725']]));
        $this->actingAs($dp)->post("/relogios/{$this->relogio->id}/importar", ['arquivo' => $arquivo])->assertSessionHas('ok');
        $this->assertSame(1, Marcacao::where('origem', 'relogio')->count());

        $this->actingAs($dp)->get("/relogios/{$this->relogio->id}")->assertOk()->assertSee('AFD00004700781000218.txt')->assertSee('Ana');
        $imp = ImportacaoAfd::first();
        $this->actingAs($dp)->get("/relogios/importacoes/{$imp->id}/arquivo")->assertOk();
        $this->actingAs($dp)->get('/espelho?colaborador_id='.$this->ana->id.'&competencia=2026-11')->assertOk();
    }

    public function test_coleta_automatica_pela_pasta(): void
    {
        $pasta = sys_get_temp_dir().'/sipgad-afd-'.uniqid();
        mkdir($pasta);
        file_put_contents("$pasta/afd1.txt", $this->afd671([[1, '2026-11-02T08:00:00-0300', '52998224725']]));
        $this->relogio->update(['pasta_afd' => $pasta]);

        $this->artisan('relogios:coletar')->assertSuccessful();
        $this->artisan('relogios:coletar')->assertSuccessful(); // mesmo arquivo: ignorado

        $this->assertSame(1, ImportacaoAfd::count());
        $this->assertSame(1, Marcacao::count());
        array_map('unlink', glob("$pasta/*"));
        rmdir($pasta);
    }
}
