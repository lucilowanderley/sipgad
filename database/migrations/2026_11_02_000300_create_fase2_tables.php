<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2: portal do colaborador (solicitações e aprovação), férias, dossiê digital e mural de comunicados.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Solicitações do colaborador: ajuste de batida, abono e atestado. Aprovadas pelo gestor (ou RH/DP).
        Schema::create('solicitacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colaborador_id')->constrained('colaboradores');
            $table->string('tipo', 20);                 // ajuste, abono, atestado
            $table->string('status', 20)->default('pendente'); // pendente, aprovada, rejeitada, cancelada
            $table->date('data');                       // dia da batida esquecida / início do abono
            $table->date('data_fim')->nullable();       // abono/atestado de vários dias
            $table->time('hora')->nullable();           // ajuste: horário a incluir
            $table->unsignedSmallInteger('minutos')->nullable(); // abono parcial (null = dia inteiro)
            $table->string('cid', 10)->nullable();      // atestado (opcional, dado sensível)
            $table->text('motivo');
            $table->string('anexo_path')->nullable();
            $table->string('anexo_nome')->nullable();
            $table->text('resposta')->nullable();
            $table->foreignId('decidido_por')->nullable()->constrained('users');
            $table->timestamp('decidido_em')->nullable();
            $table->foreignId('marcacao_id')->nullable()->constrained('marcacoes'); // ajuste aprovado gera marcação
            $table->foreignId('criado_por')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['colaborador_id', 'status']);
            $table->index(['status', 'tipo']);
        });

        // Períodos aquisitivos de férias (gerados a partir da admissão)
        Schema::create('ferias_periodos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $table->date('inicio');
            $table->date('fim');
            $table->date('limite_concessivo');            // fim + 12 meses (CLT art. 134)
            $table->unsignedSmallInteger('faltas_injustificadas')->default(0);
            $table->unsignedTinyInteger('dias_direito')->default(30); // reduzido por faltas (art. 130)
            $table->boolean('perdido')->default(false);   // art. 133 (ex.: afastamento > 6 meses)
            $table->string('observacao')->nullable();
            $table->timestamps();
            $table->unique(['colaborador_id', 'inicio']);
        });

        // Programação de gozo (até 3 períodos por aquisitivo, art. 134 §1º)
        Schema::create('ferias_programacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periodo_id')->constrained('ferias_periodos')->cascadeOnDelete();
            $table->foreignId('colaborador_id')->constrained('colaboradores');
            $table->date('inicio');
            $table->date('fim');
            $table->unsignedTinyInteger('dias');
            $table->unsignedTinyInteger('dias_abono')->default(0); // venda de até 1/3 (art. 143)
            $table->boolean('adiantamento_13')->default(false);
            $table->string('status', 20)->default('pendente'); // pendente, aprovada, rejeitada, cancelada
            $table->text('observacao')->nullable();
            $table->text('resposta')->nullable();
            $table->foreignId('decidido_por')->nullable()->constrained('users');
            $table->timestamp('decidido_em')->nullable();
            $table->foreignId('criado_por')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['colaborador_id', 'inicio']);
        });

        Schema::create('tipos_documento', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->boolean('obrigatorio')->default(false);
            $table->boolean('exige_validade')->default(false);
            $table->unsignedSmallInteger('alerta_dias')->default(30);
            $table->boolean('colaborador_envia')->default(true); // colaborador pode enviar pelo portal
            $table->timestamps();
        });

        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colaborador_id')->constrained('colaboradores');
            $table->foreignId('tipo_documento_id')->constrained('tipos_documento');
            $table->string('descricao')->nullable();
            $table->string('numero', 60)->nullable();
            $table->date('emissao')->nullable();
            $table->date('validade')->nullable();
            $table->string('arquivo_path');
            $table->string('arquivo_nome');
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('tamanho')->default(0);
            $table->string('hash', 64)->nullable();
            $table->foreignId('enviado_por')->nullable()->constrained('users');
            $table->timestamp('conferido_em')->nullable();
            $table->foreignId('conferido_por')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['colaborador_id', 'tipo_documento_id']);
            $table->index('validade');
        });

        Schema::create('comunicados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas'); // null = todas
            $table->foreignId('setor_id')->nullable()->constrained('setores');    // null = todos
            $table->string('titulo');
            $table->text('corpo');
            $table->string('categoria', 20)->default('geral'); // geral, rh, seguranca, evento, urgente
            $table->boolean('fixado')->default(false);
            $table->boolean('exige_ciencia')->default(false);
            $table->dateTime('publicar_em');
            $table->date('expira_em')->nullable();
            $table->foreignId('autor_id')->nullable()->constrained('users');
            $table->timestamps();
            $table->index('publicar_em');
        });

        Schema::create('comunicado_leituras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comunicado_id')->constrained('comunicados')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('lido_em');
            $table->timestamp('ciente_em')->nullable();
            $table->string('ip', 45)->nullable();
            $table->unique(['comunicado_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['comunicado_leituras', 'comunicados', 'documentos', 'tipos_documento', 'ferias_programacoes', 'ferias_periodos', 'solicitacoes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
