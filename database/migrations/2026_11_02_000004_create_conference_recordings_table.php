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
        Schema::create('conference_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conference_record_id')->constrained('conference_records')->cascadeOnDelete()->index();
            $table->string('recording_name', 191)->unique()->index();
            $table->string('state', 50)->default('FILE_GENERATED')->index();
            $table->string('drive_file_id', 191)->nullable()->index();
            $table->text('export_uri')->nullable();
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
        Schema::dropIfExists('conference_recordings');
    }
};
