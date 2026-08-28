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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perfil_id')->constrained('perfis')->onDelete('restrict');
            $table->foreignId('municipio_id')->nullable()->constrained('municipios_es')->nullOnDelete();
            $table->string('name', 150);
            $table->string('email', 191)->unique()->index();
            $table->string('password', 255)->nullable();
            $table->string('govbr_id', 100)->nullable()->unique()->index();
            $table->text('cpf_encrypted')->nullable();
            $table->string('hash_cpf', 64)->nullable()->unique()->index();
            $table->text('telefone_encrypted')->nullable();
            $table->string('foto_url', 255)->nullable();
            $table->boolean('ativo')->default(true)->index();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 191)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
