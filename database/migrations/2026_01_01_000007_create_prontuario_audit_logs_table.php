<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prontuario_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prontuario_id')->nullable()->constrained('prontuarios')->cascadeOnDelete()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->index();
            $table->string('acao', 50)->index(); // VIEW, CREATE, UPDATE, EXPORT_PDF, VALIDATE_QR, ANONYMIZE, UNAUTHORIZED_ACCESS
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('previous_hash', 64);
            $table->string('current_hash', 64)->index();
            $table->json('details')->nullable();
            $table->timestamp('timestamp')->useCurrent()->index();
        });

        // PostgreSQL Immutability Rules: Bloqueio estrito de UPDATE e DELETE a nivel de banco
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE RULE prontuario_audit_logs_no_update AS ON UPDATE TO prontuario_audit_logs DO INSTEAD NOTHING;');
            DB::statement('CREATE RULE prontuario_audit_logs_no_delete AS ON DELETE TO prontuario_audit_logs DO INSTEAD NOTHING;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP RULE IF EXISTS prontuario_audit_logs_no_update ON prontuario_audit_logs;');
            DB::statement('DROP RULE IF EXISTS prontuario_audit_logs_no_delete ON prontuario_audit_logs;');
        }

        Schema::dropIfExists('prontuario_audit_logs');
    }
};
