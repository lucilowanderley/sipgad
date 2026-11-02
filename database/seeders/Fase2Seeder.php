<?php

namespace Database\Seeders;

use App\Models\Colaborador;
use App\Models\Comunicado;
use App\Models\FeriasProgramacao;
use App\Models\Solicitacao;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Services\Ferias;
use Illuminate\Database\Seeder;

/**
 * Dados da Fase 2. Pode ser rodado numa base que já tem a Fase 1:
 *   php artisan db:seed --class=Fase2Seeder
 * Sempre cria os tipos de documento padrão; se encontrar os usuários de demonstração, cria exemplos.
 */
class Fase2Seeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            // nome, obrigatório, exige validade, alerta (dias), colaborador envia
            ['RG ou CNH (documento de identidade)', true, false, 30, true],
            ['CPF', true, false, 30, true],
            ['CTPS (Carteira de Trabalho Digital)', true, false, 30, true],
            ['Comprovante de residência', true, false, 30, true],
            ['Título de eleitor', false, false, 30, true],
            ['Certidão de nascimento ou casamento', false, false, 30, true],
            ['Certidão de nascimento dos filhos', false, false, 30, true],
            ['Comprovante de escolaridade', false, false, 30, true],
            ['CNH (habilitação)', false, true, 30, true],
            ['ASO - Atestado de Saúde Ocupacional', true, true, 30, false],
            ['Certificado de treinamento (NR)', false, true, 45, true],
            ['Contrato de trabalho', true, false, 30, false],
            ['Ficha de registro', false, false, 30, false],
            ['Atestado médico', false, false, 30, true],
            ['Outros', false, false, 30, true],
        ];
        foreach ($tipos as [$nome, $obrig, $validade, $alerta, $envia]) {
            TipoDocumento::firstOrCreate(['nome' => $nome], [
                'obrigatorio' => $obrig, 'exige_validade' => $validade, 'alerta_dias' => $alerta, 'colaborador_envia' => $envia,
            ]);
        }

        $admin = User::where('email', 'admin@sipgad.test')->first();
        $gestor = User::where('email', 'gestor@sipgad.test')->first()?->colaborador;
        $vigia = User::where('email', 'colaborador@sipgad.test')->first()?->colaborador;
        $analista = User::where('email', 'dp@sipgad.test')->first()?->colaborador;
        if (! $gestor || ! $vigia || ! $analista || Comunicado::exists()) {
            return;
        }

        // Admissões mais antigas para haver períodos aquisitivos completos na demonstração
        // (a jornada continua começando onde estava, então os meses anteriores não geram faltas)
        $gestor->update(['data_admissao' => today()->subMonths(23)->startOfMonth()]);
        $vigia->update(['data_admissao' => today()->subMonths(14)->startOfMonth(), 'gestor_id' => $gestor->id]);
        $analista->update(['data_admissao' => today()->subMonths(30)->startOfMonth()]);

        Comunicado::create([
            'titulo' => 'Bem-vindo ao portal do colaborador',
            'corpo' => "Agora você pode, pelo SIPGAD:\n\n• consultar o espelho de ponto e o saldo do banco de horas;\n• pedir ajuste de batida, abono ou enviar atestado;\n• programar suas férias e acompanhar a aprovação;\n• enviar seus documentos para o dossiê digital.\n\nDúvidas, procure o Departamento Pessoal.",
            'categoria' => 'rh', 'fixado' => true, 'exige_ciencia' => true, 'publicar_em' => now()->subDay(), 'autor_id' => $admin?->id,
        ]);
        Comunicado::create([
            'titulo' => 'Treinamento de brigada de incêndio',
            'corpo' => 'Na próxima semana teremos o treinamento anual da brigada. Os inscritos receberão o horário pelo gestor.',
            'categoria' => 'seguranca', 'publicar_em' => now()->subHours(3), 'expira_em' => today()->addDays(15), 'autor_id' => $admin?->id,
        ]);

        Solicitacao::create([
            'colaborador_id' => $vigia->id, 'tipo' => 'ajuste', 'data' => today()->subDays(3), 'hora' => '07:02',
            'motivo' => 'Esqueci de registrar a saída do plantão.', 'criado_por' => $vigia->user_id,
        ]);
        Solicitacao::create([
            'colaborador_id' => $analista->id, 'tipo' => 'abono', 'data' => today()->subDays(6), 'minutos' => 120,
            'motivo' => 'Consulta médica no início da manhã (declaração de comparecimento).', 'criado_por' => $analista->user_id,
        ]);

        $ferias = app(Ferias::class);
        foreach ([$gestor, $vigia, $analista] as $c) {
            $ferias->sincronizarPeriodos($c->fresh());
        }
        // Analista: 1º período já gozado; 2º período com 20 dias programados e aprovados
        $per = $analista->feriasPeriodos()->orderBy('inicio')->get();
        if ($per->count() >= 2) {
            $ini1 = $per[0]->fim->copy()->addMonths(3)->next('Monday');
            FeriasProgramacao::create(['periodo_id' => $per[0]->id, 'colaborador_id' => $analista->id, 'inicio' => $ini1,
                'fim' => $ini1->copy()->addDays(29), 'dias' => 30, 'status' => 'aprovada', 'decidido_em' => now(), 'decidido_por' => $admin?->id]);
            $ini2 = today()->addDays(40)->next('Monday');
            FeriasProgramacao::create(['periodo_id' => $per[1]->id, 'colaborador_id' => $analista->id, 'inicio' => $ini2,
                'fim' => $ini2->copy()->addDays(19), 'dias' => 20, 'dias_abono' => 10, 'status' => 'aprovada', 'decidido_em' => now(), 'decidido_por' => $admin?->id]);
        }
        // Vigilante: pedido pendente para o gestor aprovar
        $pv = $vigia->feriasPeriodos()->orderBy('inicio')->first();
        if ($pv && $pv->completo()) {
            $ini = today()->addDays(45)->next('Tuesday');
            FeriasProgramacao::create(['periodo_id' => $pv->id, 'colaborador_id' => $vigia->id, 'inicio' => $ini,
                'fim' => $ini->copy()->addDays(14), 'dias' => 15, 'status' => 'pendente', 'criado_por' => $vigia->user_id,
                'observacao' => 'Viagem em família.']);
        }
    }
}
