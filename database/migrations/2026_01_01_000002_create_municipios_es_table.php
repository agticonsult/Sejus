<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('municipios_es', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('codigo_ibge')->unique()->index();
            $table->string('nome', 100)->index();
            $table->string('microrregiao', 100)->index();
            $table->string('macrorregiao', 50)->index();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('tem_escritorio_fisico')->default(false)->index();
            $table->unsignedInteger('populacao_estimada')->nullable();
            $table->unsignedInteger('total_egressos_atendidos')->default(0);
            $table->timestamps();

            $table->index(['latitude', 'longitude'], 'idx_municipios_es_coords');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('municipios_es');
    }
};
