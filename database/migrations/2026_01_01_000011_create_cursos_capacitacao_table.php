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
        Schema::create('cursos_capacitacao', function (Blueprint $table) {
            $table->id();
            $table->string('instituicao', 150)->index();
            $table->string('titulo', 150)->index();
            $table->text('descricao');
            $table->string('categoria', 50)->index(); // industrial, tecnologia, gestao, servicos, artesanato
            $table->foreignId('municipio_id')->nullable()->constrained('municipios_es')->nullOnDelete()->index();
            $table->integer('carga_horaria');
            $table->string('modalidade', 30)->default('presencial'); // presencial, ead, hibrido
            $table->decimal('bolsa_auxilio', 10, 2)->nullable();
            $table->integer('vagas_disponiveis')->default(0);
            $table->string('status', 30)->default('aberto')->index(); // aberto, em_andamento, encerrado, cancelado
            $table->string('link_inscricao', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cursos_capacitacao');
    }
};
