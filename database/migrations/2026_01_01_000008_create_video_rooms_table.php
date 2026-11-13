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
            $table->unsignedBigInteger('prontuario_id')->nullable();
            $table->unsignedBigInteger('tecnico_id')->nullable()->index();
            $table->unsignedBigInteger('egresso_id')->nullable()->index();
            $table->unsignedBigInteger('municipio_id')->nullable();
            $table->string('status', 30)->default('aguardando')->index(); // aguardando, em_andamento, encerrada, cancelada
            $table->string('prioridade', 20)->default('normal'); // normal, preferencial, urgente
            $table->string('motivo_atendimento', 150)->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('token_sala')->nullable();
            $table->timestamps();

            // Foreign Keys com nomenclatura explícita para PostgreSQL
            $table->foreign('prontuario_id', 'fk_video_rooms_prontuario_id')->references('id')->on('prontuarios')->onDelete('set null');
            $table->foreign('tecnico_id', 'fk_video_rooms_tecnico_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('egresso_id', 'fk_video_rooms_egresso_id')->references('id')->on('egressos')->onDelete('set null');
            $table->foreign('municipio_id', 'fk_video_rooms_municipio_id')->references('id')->on('municipios_es')->onDelete('set null');
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
