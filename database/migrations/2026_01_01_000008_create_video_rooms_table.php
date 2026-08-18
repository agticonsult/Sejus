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
        Schema::create('video_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_code', 64)->unique()->index();
            $table->foreignId('prontuario_id')->nullable()->constrained('prontuarios')->nullOnDelete();
            $table->foreignId('tecnico_id')->nullable()->constrained('users')->nullOnDelete()->index();
            $table->foreignId('egresso_id')->nullable()->constrained('egressos')->nullOnDelete()->index();
            $table->foreignId('municipio_id')->nullable()->constrained('municipios_es')->nullOnDelete();
            $table->string('status', 30)->default('aguardando')->index(); // aguardando, em_andamento, encerrada, cancelada
            $table->string('prioridade', 20)->default('normal'); // normal, preferencial, urgente
            $table->string('motivo_atendimento', 150)->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('token_sala')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_rooms');
    }
};
