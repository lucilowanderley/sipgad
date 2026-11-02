<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Marcações nunca são apagadas nem editadas: ajustes geram novos registros
        // e marcações indevidas são apenas "desconsideradas" com motivo.
        Schema::create('marcacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('colaborador_id')->constrained('colaboradores');
            $table->unsignedBigInteger('nsr'); // número sequencial de registro por empresa
            $table->dateTime('data_hora');
            $table->string('origem', 20)->default('web'); // web, ajuste
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('hash', 64);
            $table->string('motivo')->nullable(); // obrigatório quando origem = ajuste
            $table->foreignId('criado_por')->nullable()->constrained('users');
            $table->timestamp('desconsiderada_em')->nullable();
            $table->string('motivo_desconsideracao')->nullable();
            $table->foreignId('desconsiderada_por')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['empresa_id', 'nsr']);
            $table->index(['colaborador_id', 'data_hora']);
        });

        Schema::create('periodos_fechamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('competencia', 7); // AAAA-MM
            $table->date('inicio');
            $table->date('fim');
            $table->timestamp('fechado_em');
            $table->foreignId('fechado_por')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['empresa_id', 'competencia']);
        });

        Schema::create('banco_horas_lancamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colaborador_id')->constrained('colaboradores');
            $table->foreignId('periodo_id')->nullable()->constrained('periodos_fechamento');
            $table->date('data');
            $table->integer('minutos'); // positivo = crédito, negativo = débito
            $table->string('tipo', 20); // fechamento, compensacao, pagamento, ajuste
            $table->string('descricao')->nullable();
            $table->foreignId('criado_por')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banco_horas_lancamentos');
        Schema::dropIfExists('periodos_fechamento');
        Schema::dropIfExists('marcacoes');
    }
};
