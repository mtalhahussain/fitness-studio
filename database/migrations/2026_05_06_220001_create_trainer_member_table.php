<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('unassigned_at')->nullable();

            // One trainer-member pair per gym (no duplicates)
            $table->unique(['gym_id', 'trainer_id', 'member_id']);
            $table->index(['gym_id', 'trainer_id']);
            $table->index(['gym_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_member');
    }
};
