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
        Schema::create('conference_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_room_id')->nullable()->index();
            $table->string('conference_record_name', 191)->unique()->index();
            $table->string('space_name', 191)->index();
            $table->string('status', 30)->default('ended')->index();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->timestamp('expire_time')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->json('raw_metadata')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            // Foreign Keys com nomenclatura explícita para PostgreSQL
            $table->foreign('meeting_room_id', 'fk_conference_rec_meeting_room')->references('id')->on('meeting_rooms')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conference_records');
    }
};
