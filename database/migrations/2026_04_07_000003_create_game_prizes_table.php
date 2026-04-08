<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_prizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->string('name', 100);
            $table->decimal('multiplier', 5, 2);
            $table->decimal('probability', 6, 5);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['game_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_prizes');
    }
};
