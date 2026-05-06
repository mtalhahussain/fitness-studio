<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['attendances', 'memberships', 'membership_plans', 'trainer_member'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropForeign(['gym_id']);
                $blueprint->unsignedBigInteger('gym_id')->nullable()->change();
                $blueprint->foreign('gym_id')->references('id')->on('gyms')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['attendances', 'memberships', 'membership_plans', 'trainer_member'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropForeign(['gym_id']);
                $blueprint->unsignedBigInteger('gym_id')->nullable(false)->change();
                $blueprint->foreign('gym_id')->references('id')->on('gyms')->cascadeOnDelete();
            });
        }
    }
};
