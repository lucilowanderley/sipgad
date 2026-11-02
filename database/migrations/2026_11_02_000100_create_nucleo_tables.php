<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('razao_social');
            $table->string('nome_fantasia')->nullable();
            $table->string('cnpj', 18)->unique();
            $table->string('endereco')->nullable();
            $table->string('cidade')->nullable();
            $table->string('uf', 2)->nullable();
            $table->boolean('banco_horas_ativo')->default(true);
            // Tolerância diária (CLT art. 58 §1º: até 10 min/dia não são computados)
            $table->unsignedSmallInteger('tolerancia_diaria_minutos')->default(10);
            $table->unsignedBigInteger('ultimo_nsr')->default(0);
            $table->timestamps();
        });

        Schema::create('setores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('nome');
            $table->timestamps();
        });

        Schema::create('cargos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('nome');
            $table->string('cbo', 10)->nullable();
            $table->timestamps();
        });

        Schema::create('jornadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('nome');
            // semanal: horários por dia da semana | revezamento: ex. 12x36
            $table->string('tipo', 20)->default('semanal');
            $table->unsignedSmallInteger('horas_trabalho')->nullable();  // revezamento: 12
            $table->unsignedSmallInteger('horas_folga')->nullable();     // revezamento: 36
            $table->time('entrada')->nullable();                          // revezamento
            $table->unsignedSmallInteger('intervalo_minutos')->default(0); // revezamento
            $table->timestamps();
        });

        Schema::create('jornada_dias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jornada_id')->constrained('jornadas')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana'); // 0 = domingo ... 6 = sábado
            $table->time('entrada1')->nullable();
            $table->time('saida1')->nullable();
            $table->time('entrada2')->nullable();
            $table->time('saida2')->nullable();
            $table->unique(['jornada_id', 'dia_semana']);
        });

        Schema::create('colaboradores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('setor_id')->nullable()->constrained('setores');
            $table->foreignId('cargo_id')->nullable()->constrained('cargos');
            $table->foreignId('gestor_id')->nullable()->constrained('colaboradores');
            $table->foreignId('user_id')->nullable()->unique()->constrained('users');
            $table->string('matricula', 30);
            $table->string('nome');
            $table->string('cpf', 14)->unique();
            $table->string('pis', 14)->nullable();
            $table->date('data_nascimento')->nullable();
            $table->string('email')->nullable();
            $table->string('telefone', 20)->nullable();
            $table->date('data_admissao');
            $table->date('data_demissao')->nullable();
            $table->string('tipo_contrato', 20)->default('clt'); // clt, estagio, temporario, pj
            $table->decimal('salario', 12, 2)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->unique(['empresa_id', 'matricula']);
        });

        // Histórico de jornada por colaborador (permite recalcular meses antigos corretamente)
        Schema::create('colaborador_jornadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $table->foreignId('jornada_id')->constrained('jornadas');
            $table->date('inicio');
            $table->date('fim')->nullable();
            $table->date('data_base_revezamento')->nullable(); // 1º dia trabalhado no 12x36
            $table->timestamps();
        });

        Schema::create('feriados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas'); // null = todas
            $table->date('data');
            $table->string('descricao');
            $table->timestamps();
        });

        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('acao', 30);
            $table->string('entidade');
            $table->unsignedBigInteger('entidade_id')->nullable();
            $table->json('antes')->nullable();
            $table->json('depois')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entidade', 'entidade_id']);
        });
    }

    public function down(): void
    {
        foreach (['auditorias', 'feriados', 'colaborador_jornadas', 'colaboradores', 'jornada_dias', 'jornadas', 'cargos', 'setores', 'empresas'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
