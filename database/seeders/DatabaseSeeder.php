<?php

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\Colaborador;
use App\Models\ColaboradorJornada;
use App\Models\Empresa;
use App\Models\Feriado;
use App\Models\Jornada;
use App\Models\Setor;
use App\Models\User;
use App\Services\RegistroPonto;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create(['name' => 'Administrador', 'email' => 'admin@sipgad.test', 'password' => 'admin123', 'perfil' => 'admin']);

        foreach ($this->feriadosNacionais(now()->year) as $data => $descricao) {
            Feriado::create(['data' => $data, 'descricao' => $descricao]);
        }

        if ($this->command?->confirm('Criar dados de demonstração (empresa, colaboradores e batidas)?', true)) {
            $this->demonstracao();
        }
        $this->call(Fase2Seeder::class);
    }

    private function demonstracao(): void
    {
        $empresa = Empresa::create(['razao_social' => 'Empresa Demonstração Ltda', 'nome_fantasia' => 'Demo', 'cnpj' => '00.000.000/0001-91', 'cidade' => 'Recife', 'uf' => 'PE']);
        $adm = Setor::create(['empresa_id' => $empresa->id, 'nome' => 'Administrativo']);
        $op = Setor::create(['empresa_id' => $empresa->id, 'nome' => 'Operação']);
        $cAnalista = Cargo::create(['empresa_id' => $empresa->id, 'nome' => 'Analista de DP', 'cbo' => '4110-10']);
        $cGerente = Cargo::create(['empresa_id' => $empresa->id, 'nome' => 'Gerente Administrativo', 'cbo' => '1421-05']);
        $cVigia = Cargo::create(['empresa_id' => $empresa->id, 'nome' => 'Vigilante', 'cbo' => '5173-30']);

        $comercial = Jornada::create(['empresa_id' => $empresa->id, 'nome' => 'Comercial 44h (seg-sex + sáb manhã)', 'tipo' => 'semanal']);
        foreach ([1, 2, 3, 4, 5] as $d) {
            $comercial->dias()->create(['dia_semana' => $d, 'entrada1' => '08:00', 'saida1' => '12:00', 'entrada2' => '13:00', 'saida2' => '17:48']);
        }
        $comercial->dias()->create(['dia_semana' => 6, 'entrada1' => '08:00', 'saida1' => '12:00']);
        $noturna = Jornada::create(['empresa_id' => $empresa->id, 'nome' => '12x36 noturno', 'tipo' => 'revezamento', 'horas_trabalho' => 12, 'horas_folga' => 36, 'entrada' => '19:00', 'intervalo_minutos' => 60]);

        $inicio = now()->subMonths(2)->startOfMonth();
        $pessoas = [
            ['Gestor Demonstração', '529.982.247-25', $adm, $cGerente, $comercial, 'gestor@sipgad.test', 'gestor', null],
            ['Analista Demonstração', '111.444.777-35', $adm, $cAnalista, $comercial, 'dp@sipgad.test', 'dp', 0],
            ['Vigilante Demonstração', '390.533.447-05', $op, $cVigia, $noturna, 'colaborador@sipgad.test', 'colaborador', 0],
        ];
        $criados = [];
        foreach ($pessoas as $i => [$nome, $cpf, $setor, $cargo, $jornada, $email, $perfil, $gestor]) {
            $user = User::create(['name' => $nome, 'email' => $email, 'password' => 'senha123', 'perfil' => $perfil]);
            $c = Colaborador::create([
                'empresa_id' => $empresa->id, 'setor_id' => $setor->id, 'cargo_id' => $cargo->id, 'user_id' => $user->id,
                'gestor_id' => $gestor !== null ? $criados[$gestor]->id : null,
                'matricula' => str_pad($i + 1, 4, '0', STR_PAD_LEFT), 'nome' => $nome, 'cpf' => $cpf, 'data_admissao' => $inicio,
            ]);
            ColaboradorJornada::create(['colaborador_id' => $c->id, 'jornada_id' => $jornada->id, 'inicio' => $inicio, 'data_base_revezamento' => $inicio]);
            $criados[] = $c;
        }

        // Batidas simuladas desde o início do mês passado até ontem, com pequenas variações
        $registro = app(RegistroPonto::class);
        $marcacoes = [];
        foreach ($criados as $c) {
            $c->load('jornadas.jornada.dias');
            foreach (CarbonPeriod::create(now()->subMonth()->startOfMonth(), today()->subDay()) as $dia) {
                $vinculo = $c->jornadaEm($dia);
                if (! $vinculo || Feriado::whereDate('data', $dia)->exists() || mt_rand(1, 30) === 1) {
                    continue; // feriado ou falta ocasional
                }
                foreach ($vinculo->jornada->intervalosPrevistos($dia, $vinculo->data_base_revezamento) as [$e, $s]) {
                    $marcacoes[] = [$c, $e->copy()->addMinutes(mt_rand(-8, 6))];
                    $marcacoes[] = [$c, $s->copy()->addMinutes(mt_rand(-5, 25))];
                }
            }
        }
        usort($marcacoes, fn ($a, $b) => $a[1] <=> $b[1]);
        foreach ($marcacoes as [$c, $quando]) {
            if ($quando->isPast()) {
                $registro->registrar($c, $quando, ['origem' => 'web', 'ip' => '127.0.0.1']);
            }
        }
    }

    /** Feriados nacionais fixos e móveis do ano. */
    private function feriadosNacionais(int $ano): array
    {
        // Algoritmo de Meeus/Jones/Butcher para o domingo de Páscoa
        $a = $ano % 19; $b = intdiv($ano, 100); $c = $ano % 100; $d = intdiv($b, 4); $e = $b % 4;
        $g = intdiv(8 * $b + 13, 25); $h = (19 * $a + $b - $d - $g + 15) % 30; $i = intdiv($c, 4); $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7; $m = intdiv($a + 11 * $h + 19 * $l, 433);
        $mes = intdiv($h + $l - 7 * $m + 90, 25); $dia = ($h + $l - 7 * $m + 33 * $mes + 19) % 32;
        $pascoa = now()->setDate($ano, $mes, $dia)->startOfDay();
        $f = [
            "$ano-01-01" => 'Confraternização Universal', "$ano-04-21" => 'Tiradentes', "$ano-05-01" => 'Dia do Trabalho',
            "$ano-09-07" => 'Independência do Brasil', "$ano-10-12" => 'Nossa Senhora Aparecida', "$ano-11-02" => 'Finados',
            "$ano-11-15" => 'Proclamação da República', "$ano-11-20" => 'Dia Nacional de Zumbi e da Consciência Negra', "$ano-12-25" => 'Natal',
            $pascoa->copy()->subDays(2)->toDateString() => 'Sexta-feira Santa',
            $pascoa->copy()->subDays(47)->toDateString() => 'Carnaval (ponto facultativo)',
            $pascoa->copy()->addDays(60)->toDateString() => 'Corpus Christi (ponto facultativo)',
        ];
        ksort($f);
        return $f;
    }
}
