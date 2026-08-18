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
        Schema::create('video_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_room_id')->constrained('video_rooms')->cascadeOnDelete()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->index();
            $table->string('peer_id', 64)->nullable();
            $table->string('role', 30); // tecnico, egresso, familiar, observador
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->decimal('mos_score', 4, 2)->nullable(); // 1.00 a 5.00
            $table->decimal('packet_loss', 5, 2)->nullable(); // %
            $table->decimal('jitter', 6, 2)->nullable(); // ms
            $table->decimal('rtt_ms', 6, 2)->nullable(); // ms
            $table->json('telemetry_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_attendees');
    }
};
