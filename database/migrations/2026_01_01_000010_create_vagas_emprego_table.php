<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vagas_emprego', function (Blueprint $table) {
            $table->id();
            $table->string('empresa', 150)->index();
            $table->string('titulo', 150)->index();
            $table->text('descricao');
            $table->string('categoria', 50)->index(); // logistica, construcao_civil, agropecuaria, servicos, industria, comercio
            $table->foreignId('municipio_id')->constrained('municipios_es')->restrictOnDelete()->index();
            $table->decimal('salario', 10, 2)->nullable();
            $table->string('regime_contratacao', 30)->default('CLT'); // CLT, PJ, Temporario, Estagio
            $table->boolean('afirmativa_egresso')->default(true)->index();
            $table->boolean('empresa_amiga_reintegracao')->default(true);
            $table->string('escolaridade_minima', 50)->default('sem_exigencia');
            $table->integer('vagas_totais')->default(1);
            $table->integer('vagas_preenchidas')->default(0);
            $table->string('status', 30)->default('aberta')->index(); // aberta, preenchida, pausada, cancelada
            $table->json('beneficios')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vagas_emprego');
    }
};
