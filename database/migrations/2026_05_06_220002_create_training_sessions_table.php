<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_mins')->default(60);
            $table->enum('session_type', ['personal', 'group'])->default('personal');
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])->default('scheduled');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['gym_id', 'trainer_id']);
            $table->index(['gym_id', 'member_id']);
            $table->index(['gym_id', 'scheduled_at']);
            $table->index(['trainer_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
};
