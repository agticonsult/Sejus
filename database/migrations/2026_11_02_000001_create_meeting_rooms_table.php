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
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('prontuario_id')->nullable()->index();
            $table->timestamps();

            // Foreign Keys com nomenclatura explícita para PostgreSQL
            $table->foreign('created_by', 'fk_meeting_rooms_created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('prontuario_id', 'fk_meeting_rooms_prontuario_id')->references('id')->on('prontuarios')->onDelete('set null');
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
