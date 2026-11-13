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
        Schema::create('meeting_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('space_name', 100)->unique()->index();
            $table->string('meeting_uri', 255);
            $table->string('meeting_code', 50)->index();
            $table->string('access_type', 30)->default('OPEN');
            $table->string('entry_point_access', 30)->default('CREATOR_APP_ONLY');
            $table->string('moderation', 20)->default('ON');
            $table->string('chat_restriction', 30)->default('HOSTS_ONLY');
            $table->string('present_restriction', 30)->default('HOSTS_ONLY');
            $table->string('default_join_as_viewer_type', 20)->default('ON');
            $table->string('status', 30)->default('active')->index();
            $table->json('raw_config')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->index();
            $table->foreignId('prontuario_id')->nullable()->constrained('prontuarios')->nullOnDelete()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_rooms');
    }
};
