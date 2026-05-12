<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('trainer_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->index(['gym_id', 'trainer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['trainer_id']);
            $table->dropIndex(['gym_id', 'trainer_id']);
            $table->dropColumn('trainer_id');
        });
    }
};
