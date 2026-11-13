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
        Schema::create('conference_transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conference_record_id')->constrained('conference_records', 'id', 'fk_conference_transcr_conferen')->cascadeOnDelete()->index();
            $table->string('transcript_name', 191)->unique()->index();
            $table->string('state', 50)->default('FILE_GENERATED')->index();
            $table->string('docs_document_id', 191)->nullable()->index();
            $table->text('export_uri')->nullable();
            $table->longText('full_text')->nullable();
            $table->longText('content_text')->nullable();
            $table->integer('entries_count')->default(0);
            $table->json('raw_entries')->nullable();
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
        Schema::dropIfExists('conference_transcripts');
    }
};
