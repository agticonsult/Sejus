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
        Schema::create('conhecimentos', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100)->unique()->index();
            $table->text('descricao')->nullable();
            $table->string('categoria', 50)->nullable()->index();
            $table->boolean('ativo')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conhecimentos');
    }
};
