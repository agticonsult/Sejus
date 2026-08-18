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
            $table->foreignId('egresso_id')->unique()->constrained('egressos')->cascadeOnDelete();
            $table->foreignId('tecnico_responsavel_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('situacao', 30)->default('ativo')->index(); // ativo, em_acompanhamento, arquivado, desligado
            $table->text('resumo_diagnostico')->nullable();
            $table->text('meta_plano_individual')->nullable();
            $table->timestamp('data_abertura')->useCurrent();
            $table->timestamps();
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
