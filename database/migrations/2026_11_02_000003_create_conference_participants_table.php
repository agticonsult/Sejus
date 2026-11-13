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
        Schema::create('conference_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conference_record_id')->index();
            $table->string('participant_name', 191)->index();
            $table->string('display_name', 255)->nullable();
            $table->string('user_identifier', 191)->nullable()->index();
            $table->string('user_type', 30)->default('signedin')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamp('earliest_start_time')->nullable();
            $table->timestamp('latest_end_time')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->json('raw_metadata')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            // Foreign Keys com nomenclatura explícita para PostgreSQL
            $table->foreign('conference_record_id', 'fk_conference_par_conference_r')->references('id')->on('conference_records')->onDelete('cascade');
            $table->foreign('user_id', 'fk_conference_par_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conference_participants');
    }
};
