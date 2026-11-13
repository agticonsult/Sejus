<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vagas_emprego', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();
            $table->date('data_inicio')->nullable()->after('vagas_preenchidas');
            $table->boolean('inicio_imediato')->default(false)->after('data_inicio');
            $table->date('data_fim')->nullable()->after('inicio_imediato');
            $table->boolean('fim_permanente')->default(false)->after('data_fim');

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('vagas_emprego', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->dropColumn([
                'user_id',
                'data_inicio',
                'inicio_imediato',
                'data_fim',
                'fim_permanente',
            ]);
        });
    }
};
