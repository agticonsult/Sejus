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
        Schema::create('egresso_conhecimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('egresso_id')->constrained('egressos', 'id', 'fk_egresso_conhecimen_egresso_')->cascadeOnDelete()->index();
            $table->foreignId('conhecimento_id')->constrained('conhecimentos', 'id', 'fk_egresso_conhecimen_conhecim')->cascadeOnDelete()->index();
            $table->string('nivel', 20)->default('basico')->index(); // basico, intermediario, avancado
            $table->boolean('necessita_treinamento')->default(false)->index();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->unique(['egresso_id', 'conhecimento_id'], 'egresso_conhecimento_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('egresso_conhecimentos');
    }
};
