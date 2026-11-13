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
        Schema::create('egressos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('nome_completo', 150)->index();
            $table->string('nome_social', 150)->nullable();
            $table->date('data_nascimento')->nullable();
            $table->text('cpf_encrypted');
            $table->string('hash_cpf', 64)->unique()->index();
            $table->text('rg_encrypted')->nullable();
            $table->text('filiacao_mae_encrypted')->nullable();
            $table->unsignedBigInteger('municipio_residencia_id');
            $table->text('endereco_encrypted')->nullable();
            $table->text('telefone_encrypted')->nullable();
            $table->string('escolaridade', 50)->nullable();
            $table->string('status_penal', 50)->default('egresso')->index(); // egresso, livramento_condicional, regime_aberto, extinta_pena
            $table->string('unidade_prisional_origem', 150)->nullable();
            $table->string('numero_processo_execucao', 100)->nullable();
            $table->json('vulnerabilidades')->nullable();
            $table->boolean('consentimento_geolocalizacao')->default(false);
            $table->boolean('consentimento_compartilhamento')->default(false)->index();
            $table->timestamp('termo_aceito_em')->nullable();
            $table->timestamps();

            // Foreign Keys com nomenclatura explícita para PostgreSQL
            $table->foreign('user_id', 'fk_egressos_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('municipio_residencia_id', 'fk_egressos_municipio_residenc')->references('id')->on('municipios_es')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('egressos');
    }
};
