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
        Schema::create('prontuario_timeline', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prontuario_id')->index();
            $table->string('tipo_evento', 50)->index(); // acolhimento_video, atendimento_presencial, encaminhamento_vaga, inscricao_curso, emissao_carteira, solicitacao_documento, parecer_tecnico
            $table->string('titulo', 150);
            $table->text('descricao');
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('responsavel_id')->index();
            $table->timestamp('data_evento')->useCurrent()->index();
            $table->timestamps();

            $table->foreign('prontuario_id', 'fk_timeline_prontuario')->references('id')->on('prontuarios')->cascadeOnDelete();
            $table->foreign('responsavel_id', 'fk_timeline_responsavel')->references('id')->on('users')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prontuario_timeline');
    }
};
