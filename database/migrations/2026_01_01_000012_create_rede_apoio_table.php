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
        Schema::create('rede_apoio', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 150)->index();
            $table->string('tipo', 30)->index(); // CRAS, CREAS, SINE, CAPS, CASA_CIDADAO, DEFENSORIA
            $table->foreignId('municipio_id')->constrained('municipios_es')->cascadeOnDelete()->index();
            $table->string('endereco', 255);
            $table->string('telefone', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('horario_funcionamento', 100)->nullable();
            $table->json('servicos_oferecidos')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('ativo')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rede_apoio');
    }
};
