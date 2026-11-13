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
        Schema::create('prontuarios', function (Blueprint $table) {
            $table->id();
            $table->string('numero_prontuario', 30)->unique()->index();
            $table->unsignedBigInteger('egresso_id');
            $table->unsignedBigInteger('tecnico_responsavel_id')->nullable();
            $table->string('situacao', 30)->default('ativo')->index(); // ativo, em_acompanhamento, arquivado, desligado
            $table->text('resumo_diagnostico')->nullable();
            $table->text('meta_plano_individual')->nullable();
            $table->timestamp('data_abertura')->useCurrent();
            $table->timestamps();

            // Foreign Keys com nomenclatura explícita para PostgreSQL
            $table->foreign('egresso_id', 'fk_prontuarios_egresso_id')->references('id')->on('egressos')->onDelete('cascade');
            $table->foreign('tecnico_responsavel_id', 'fk_prontuarios_tecnico_respons')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prontuarios');
    }
};
