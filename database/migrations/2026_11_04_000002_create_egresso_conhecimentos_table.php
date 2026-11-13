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
            $table->unsignedBigInteger('egresso_id')->index();
            $table->unsignedBigInteger('conhecimento_id')->index();
            $table->string('nivel', 20)->default('basico')->index(); // basico, intermediario, avancado
            $table->boolean('necessita_treinamento')->default(false)->index();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->unique(['egresso_id', 'conhecimento_id'], 'egresso_conhecimento_unique');

            // Foreign Keys com nomenclatura explícita para PostgreSQL
            $table->foreign('egresso_id', 'fk_egresso_conhec_egresso_id')->references('id')->on('egressos')->onDelete('cascade');
            $table->foreign('conhecimento_id', 'fk_egresso_conhec_conhecimento')->references('id')->on('conhecimentos')->onDelete('cascade');
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
