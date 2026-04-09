<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('golden_tree_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('stage')->default(0);
            $table->unsignedBigInteger('growth_seconds')->default(0);
            $table->unsignedBigInteger('banked_tp')->default(0);
            $table->unsignedBigInteger('total_generated')->default(0);
            $table->unsignedInteger('events_attended')->default(0);
            $table->timestamp('last_processed_at')->nullable();
            $table->timestamp('last_event_check_at')->nullable();
            $table->string('active_event_id', 32)->nullable();
            $table->timestamp('active_event_expires_at')->nullable();
            $table->timestamp('extra_pause_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('golden_tree_states');
    }
};
