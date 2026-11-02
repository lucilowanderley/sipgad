<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Relógios de ponto físicos (REP-C, ex.: Dimep SMART 671 S) ou outros coletores.
        Schema::create('relogios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('nome', 100);
            $table->string('fabricante', 60)->default('Dimep');
            $table->string('modelo', 60)->nullable();
            $table->char('tipo_rep', 1)->default('C'); // C, A ou P (Portaria 671)
            $table->string('numero_fabricacao', 17)->nullable(); // 17 dígitos impressos no AFD
            $table->string('local')->nullable();
            $table->string('ip', 45)->nullable();
            $table->unsignedInteger('porta')->nullable();
            $table->string('pasta_afd')->nullable(); // coleta automática de arquivos AFD
            $table->unsignedBigInteger('ultimo_nsr')->default(0);
            $table->timestamp('ultima_coleta_em')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->unique(['fabricante', 'numero_fabricacao']);
        });

        Schema::create('importacoes_afd', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relogio_id')->constrained('relogios');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('origem', 20); // upload, pasta, reprocessamento
            $table->string('arquivo_nome');
            $table->string('arquivo_path');
            $table->char('arquivo_hash', 64);
            $table->string('leiaute', 10)->nullable(); // 671 ou 1510
            $table->unsignedBigInteger('nsr_inicial')->nullable();
            $table->unsignedBigInteger('nsr_final')->nullable();
            $table->unsignedInteger('marcacoes_arquivo')->default(0);
            $table->unsignedInteger('importadas')->default(0);
            $table->unsignedInteger('duplicadas')->default(0);
            $table->unsignedInteger('pendentes')->default(0); // sem colaborador ou rejeitadas
            $table->json('pendencias')->nullable();
            $table->timestamps();
            $table->index(['relogio_id', 'arquivo_hash']);
        });

        Schema::table('marcacoes', function (Blueprint $table) {
            $table->foreignId('relogio_id')->nullable()->after('origem')->constrained('relogios');
            $table->unsignedBigInteger('nsr_relogio')->nullable()->after('relogio_id');
            $table->unique(['relogio_id', 'nsr_relogio']);
        });
    }

    public function down(): void
    {
        Schema::table('marcacoes', function (Blueprint $table) {
            $table->dropForeign(['relogio_id']);
            $table->dropUnique(['relogio_id', 'nsr_relogio']);
            $table->dropColumn(['relogio_id', 'nsr_relogio']);
        });
        Schema::dropIfExists('importacoes_afd');
        Schema::dropIfExists('relogios');
    }
};
