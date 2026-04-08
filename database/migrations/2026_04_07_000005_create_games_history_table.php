<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_prize_id')->nullable()->constrained('game_prizes')->onDelete('set null');
            $table->integer('bet');
            $table->integer('prize')->default(0);
            $table->bigInteger('balance_before');
            $table->bigInteger('balance_after');
            $table->timestamp('played_at');
            $table->timestamps();

            $table->index(['user_id', 'played_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games_history');
    }
};
