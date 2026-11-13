<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaga_conhecimento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vaga_id')
                ->constrained('vagas_emprego')
                ->cascadeOnDelete();
            $table->foreignId('conhecimento_id')
                ->constrained('conhecimentos')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['vaga_id', 'conhecimento_id']);
            $table->index('conhecimento_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaga_conhecimento');
    }
};
