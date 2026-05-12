<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_commission_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('commission_rate', 5, 2)->default(50.00);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'trainer_id', 'effective_from'], 'tcc_gym_trainer_from_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_commission_configs');
    }
};
