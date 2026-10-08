<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_devices', fn (Blueprint $table) => $table->json('acc_push_state')->nullable());
    }

    public function down(): void
    {
        Schema::table('biometric_devices', fn (Blueprint $table) => $table->dropColumn('acc_push_state'));
    }
};
