<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainer_profiles', function (Blueprint $table) {
            $table->dropForeign(['gym_id']);
            $table->unsignedBigInteger('gym_id')->nullable()->change();
            $table->foreign('gym_id')->references('id')->on('gyms')->nullOnDelete();
        });

        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropForeign(['gym_id']);
            $table->unsignedBigInteger('gym_id')->nullable()->change();
            $table->foreign('gym_id')->references('id')->on('gyms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trainer_profiles', function (Blueprint $table) {
            $table->dropForeign(['gym_id']);
            $table->unsignedBigInteger('gym_id')->nullable(false)->change();
            $table->foreign('gym_id')->references('id')->on('gyms')->cascadeOnDelete();
        });

        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropForeign(['gym_id']);
            $table->unsignedBigInteger('gym_id')->nullable(false)->change();
            $table->foreign('gym_id')->references('id')->on('gyms')->cascadeOnDelete();
        });
    }
};
