<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hilo_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->integer('bet');
            $table->unsignedBigInteger('starting_balance');
            $table->unsignedTinyInteger('current_rank');
            $table->string('current_suit', 16);
            $table->unsignedInteger('streak')->default(0);
            $table->decimal('current_multiplier', 12, 4)->default(1);
            $table->unsignedBigInteger('potential_payout')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hilo_rounds');
    }
};
