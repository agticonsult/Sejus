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
        Schema::create('conference_transcript_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conference_transcript_id')->constrained('conference_transcripts', 'id', 'fk_conference_transcr_conferen')->cascadeOnDelete()->index();
            $table->string('entry_name', 191)->index();
            $table->string('participant_name', 191)->nullable()->index();
            $table->string('speaker_name', 255)->nullable();
            $table->text('text');
            $table->string('language_code', 20)->nullable()->default('pt-BR');
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->json('raw_metadata')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conference_transcript_entries');
    }
};
