<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->dateTime('check_in_time');
            $table->dateTime('check_out_time')->nullable();

            // Biometric support
            $table->enum('source', ['manual', 'biometric'])->default('manual');
            $table->string('device_user_id')->nullable()->comment('ZKTeco device enrollment number');

            $table->timestamps();

            // Prevent duplicate biometric logs from the same device
            $table->unique(['user_id', 'check_in_time', 'source'], 'unique_checkin_per_source');

            $table->index(['gym_id', 'user_id']);
            $table->index(['gym_id', 'source']);
            // Partial-index equivalent: find open check-ins quickly
            $table->index(['user_id', 'check_out_time']);
            $table->index('check_in_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
